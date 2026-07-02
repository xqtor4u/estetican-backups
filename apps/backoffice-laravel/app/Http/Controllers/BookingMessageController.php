<?php

namespace App\Http\Controllers;

use App\Models\BookingMessage;
use App\Models\SpaBooking;
use App\Models\WhatsAppTemplate;
use App\Support\Pages\WhatsAppPage;
use App\Support\WhatsApp\PhoneNormalizer;
use App\Support\WhatsApp\TemplateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $templates = WhatsAppTemplate::where('is_active', true)->orderBy('name')->get();

        return view('whatsapp.bandeja.index', [
            'rows' => $rows,
            'templates' => $templates,
            'date' => $date,
            'page' => WhatsAppPage::bandeja(),
        ]);
    }

    public function store(Request $request, SpaBooking $booking): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_template_id' => ['required', 'exists:whatsapp_templates,id'],
        ]);

        $template = WhatsAppTemplate::findOrFail($validated['whatsapp_template_id']);

        $booking->loadMissing(['pet.client.phones', 'services.service']);

        $client = $booking->pet?->client;
        $rawPhone = $client ? PhoneNormalizer::bestPhoneFor($client) : null;
        $waNumber = PhoneNormalizer::toWhatsAppNumber($rawPhone);

        if (! $waNumber) {
            return response()->json([
                'message' => 'El cliente no tiene un teléfono válido de 10 dígitos (MX) registrado.',
            ], 422);
        }

        $message = TemplateResolver::resolve($template->body, $booking);
        $waLink = 'https://wa.me/'.$waNumber.'?text='.rawurlencode($message);

        $bookingMessage = BookingMessage::create([
            'spa_booking_id' => $booking->id,
            'whatsapp_template_id' => $template->id,
            'phone_number' => $waNumber,
            'message_body' => $message,
            'wa_link' => $waLink,
            'sent_by_user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        return response()->json([
            'wa_link' => $waLink,
            'sent_at' => $bookingMessage->sent_at,
        ]);
    }
}
