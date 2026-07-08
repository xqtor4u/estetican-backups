<?php

namespace App\Http\Controllers;

use App\Models\BookingMessage;
use App\Models\SpaBooking;
use App\Models\WhatsAppTemplate;
use App\Support\Pages\WhatsAppPage;
use App\Support\SystemSettings\SystemSettings;
use App\Support\WhatsApp\PhoneNormalizer;
use App\Support\WhatsApp\TemplateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BookingMessageController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->query('date', now()->toDateString());

        $bookings = SpaBooking::whereDate('scheduled_at', $date)
            ->whereNotIn('status', ['cancelled'])
            ->with([
                'pet.client.phones',
                'services.service',
                'messages' => fn ($q) => $q->whereDate('sent_at', now()->toDateString()),
            ])
            ->orderBy('scheduled_at')
            ->get();

        $rows = $bookings->map(function (SpaBooking $booking) {
            $client = $booking->pet?->client;
            $rawPhone = $client ? PhoneNormalizer::bestPhoneFor($client) : null;

            return (object) [
                'booking' => $booking,
                'wa_number' => PhoneNormalizer::toWhatsAppNumber($rawPhone),
                'raw_phone' => $rawPhone,
                'already_sent_today' => $booking->messages->isNotEmpty(),
            ];
        });

        $templates = WhatsAppTemplate::where('is_active', true)->where('context', 'cita')->orderBy('name')->get();

        $monthAnchor = Carbon::parse($date)->startOfMonth();

        return view('whatsapp.bandeja.index', [
            'rows' => $rows,
            'templates' => $templates,
            'templateVariables' => TemplateResolver::availableVariables('cita'),
            'date' => $date,
            'page' => WhatsAppPage::bandeja(),
            'calendarDays' => $this->buildMonthCalendar($monthAnchor),
            'monthAnchor' => $monthAnchor,
            'monthLabel' => $monthAnchor->translatedFormat('F Y'),
        ]);
    }

    /**
     * @return array<int, array{date: Carbon, is_today: bool, is_outside_month: bool, counts: array{completadas: int, recordatorio_pendiente: int, en_riesgo: int}}>
     */
    private function buildMonthCalendar(Carbon $monthAnchor): array
    {
        $rangeStart = $monthAnchor->copy()->startOfWeek(Carbon::MONDAY);
        $rangeEnd = $monthAnchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $graceMinutes = (int) (app(SystemSettings::class)->all()['booking_grace_minutes'] ?? 15);

        $bookings = SpaBooking::whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
            ->withExists(['messages as has_message'])
            ->get(['id', 'scheduled_at', 'status']);

        $bookingsByDate = $bookings->groupBy(fn (SpaBooking $b) => $b->scheduled_at->format('Y-m-d'));

        $calendarDays = [];
        $cursor = $rangeStart->copy()->startOfDay();
        $stop = $rangeEnd->copy()->startOfDay();

        while ($cursor->lte($stop)) {
            $dayBookings = $bookingsByDate->get($cursor->format('Y-m-d'), collect());

            $counts = [
                'completadas' => $dayBookings->filter(fn ($b) => $b->status === 'completed')->count(),
                'recordatorio_pendiente' => $dayBookings->filter(fn ($b) => in_array($b->status, ['scheduled', 'work_order'], true) && ! $b->has_message)->count(),
                'en_riesgo' => $dayBookings->filter(fn ($b) => in_array($b->status, ['scheduled', 'work_order'], true) && $b->scheduled_at->copy()->addMinutes($graceMinutes)->isPast())->count(),
            ];

            $calendarDays[] = [
                'date' => $cursor->copy(),
                'is_today' => $cursor->isToday(),
                'is_outside_month' => ! $cursor->isSameMonth($monthAnchor),
                'counts' => $counts,
            ];

            $cursor->addDay();
        }

        return $calendarDays;
    }

    public function preview(Request $request, SpaBooking $booking): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_template_id' => ['required', 'exists:whatsapp_templates,id'],
        ]);

        $template = WhatsAppTemplate::findOrFail($validated['whatsapp_template_id']);

        $resolved = $this->resolveMessage($booking, $template);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        return response()->json(['message' => $resolved['message']]);
    }

    public function store(Request $request, SpaBooking $booking): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_template_id' => ['required', 'exists:whatsapp_templates,id'],
        ]);

        $template = WhatsAppTemplate::findOrFail($validated['whatsapp_template_id']);

        $resolved = $this->resolveMessage($booking, $template);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $waLink = 'https://wa.me/'.$resolved['wa_number'].'?text='.rawurlencode($resolved['message']);

        $bookingMessage = BookingMessage::create([
            'spa_booking_id' => $booking->id,
            'whatsapp_template_id' => $template->id,
            'phone_number' => $resolved['wa_number'],
            'message_body' => $resolved['message'],
            'wa_link' => $waLink,
            'sent_by_user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        return response()->json([
            'wa_link' => $waLink,
            'sent_at' => $bookingMessage->sent_at,
        ]);
    }

    /**
     * @return array{wa_number: string, message: string}|JsonResponse
     */
    private function resolveMessage(SpaBooking $booking, WhatsAppTemplate $template): array|JsonResponse
    {
        $booking->loadMissing(['pet.client.phones', 'services.service']);

        $client = $booking->pet?->client;
        $rawPhone = $client ? PhoneNormalizer::bestPhoneFor($client) : null;
        $waNumber = PhoneNormalizer::toWhatsAppNumber($rawPhone);

        if (! $waNumber) {
            return response()->json([
                'message' => 'El cliente no tiene un teléfono válido de 10 dígitos (MX) registrado.',
            ], 422);
        }

        return [
            'wa_number' => $waNumber,
            'message' => TemplateResolver::resolve($template->body, $booking),
        ];
    }
}
