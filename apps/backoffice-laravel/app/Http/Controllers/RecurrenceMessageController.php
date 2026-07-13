<?php

namespace App\Http\Controllers;

use App\Mail\TemplateMessageMail;
use App\Models\Client;
use App\Models\Pet;
use App\Models\RecurrenceMessage;
use App\Models\Service;
use App\Models\WhatsAppTemplate;
use App\Support\Pages\WhatsAppPage;
use App\Support\WhatsApp\PhoneNormalizer;
use App\Support\WhatsApp\TemplateResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RecurrenceMessageController extends Controller
{
    public function index(): View
    {
        $services = Service::where('is_active', true)
            ->whereNotNull('recurrence_days')
            ->orderBy('name')
            ->get();

        $sentToday = RecurrenceMessage::whereDate('sent_at', now()->toDateString())
            ->get(['pet_id', 'service_id'])
            ->map(fn ($m) => $m->pet_id.':'.$m->service_id)
            ->flip();

        $rows = collect();

        foreach ($services as $service) {
            $due = $this->lastServiceDatesByPet($service)
                ->filter(fn ($lastAt) => $lastAt->copy()->addDays($service->recurrence_days)->lte(now()));

            if ($due->isEmpty()) {
                continue;
            }

            $pets = Pet::with('client.phones')->whereIn('id', $due->keys())->get()->keyBy('id');

            foreach ($due as $petId => $lastAt) {
                $pet = $pets->get($petId);
                if (! $pet) {
                    continue;
                }

                $client = $pet->client;
                $rawPhone = $client ? PhoneNormalizer::bestPhoneFor($client) : null;
                $dueDate = $lastAt->copy()->addDays($service->recurrence_days);

                $rows->push((object) [
                    'key' => $petId.':'.$service->id,
                    'pet' => $pet,
                    'service' => $service,
                    'last_at' => $lastAt,
                    'days_overdue' => (int) $dueDate->diffInDays(now()),
                    'wa_number' => PhoneNormalizer::toWhatsAppNumber($rawPhone),
                    'raw_phone' => $rawPhone,
                    'already_sent_today' => $sentToday->has($petId.':'.$service->id),
                ]);
            }
        }

        $templates = WhatsAppTemplate::where('is_active', true)->where('context', 'recurrencia')->orderBy('name')->get();

        return view('whatsapp.recurrencias.index', [
            'rows' => $rows->sortByDesc('days_overdue')->values(),
            'templates' => $templates,
            'templateVariables' => TemplateResolver::availableVariables('recurrencia'),
            'page' => WhatsAppPage::recurrencias(),
        ]);
    }

    public function preview(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_template_id' => ['required', 'exists:whatsapp_templates,id'],
            'channel' => ['nullable', 'string', 'in:whatsapp,email'],
        ]);

        [$pet, $service, $template, $error] = $this->loadRecipient($key, $validated['whatsapp_template_id']);
        if ($error) {
            return $error;
        }

        if (($validated['channel'] ?? 'whatsapp') === 'email') {
            $resolved = $this->resolveEmailMessage($pet, $service, $template);
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }

            return response()->json(['message' => $resolved['message'], 'subject' => $resolved['subject']]);
        }

        $resolved = $this->resolveMessage($pet, $service, $template);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        return response()->json(['message' => $resolved['message']]);
    }

    public function store(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_template_id' => ['required', 'exists:whatsapp_templates,id'],
            'channel' => ['nullable', 'string', 'in:whatsapp,email'],
        ]);

        [$pet, $service, $template, $error] = $this->loadRecipient($key, $validated['whatsapp_template_id']);
        if ($error) {
            return $error;
        }

        $channel = $validated['channel'] ?? 'whatsapp';

        if ($channel === 'email') {
            return $this->storeEmail($request, $pet, $service, $template);
        }

        $resolved = $this->resolveMessage($pet, $service, $template);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $waLink = 'https://wa.me/'.$resolved['wa_number'].'?text='.rawurlencode($resolved['message']);

        $recurrenceMessage = RecurrenceMessage::create([
            'pet_id' => $pet->id,
            'service_id' => $service->id,
            'whatsapp_template_id' => $template->id,
            'channel' => 'whatsapp',
            'phone_number' => $resolved['wa_number'],
            'message_body' => $resolved['message'],
            'wa_link' => $waLink,
            'sent_by_user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        return response()->json([
            'wa_link' => $waLink,
            'sent_at' => $recurrenceMessage->sent_at,
        ]);
    }

    private function storeEmail(Request $request, Pet $pet, Service $service, WhatsAppTemplate $template): JsonResponse
    {
        $resolved = $this->resolveEmailMessage($pet, $service, $template);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        try {
            Mail::to($resolved['to'])->send(new TemplateMessageMail($resolved['subject'], $resolved['message'], $resolved['client']));
        } catch (\Throwable $e) {
            Log::error('Error enviando correo de plantilla (recurrencia): '.$e->getMessage());

            return response()->json([
                'message' => 'No se pudo enviar el correo. Revisa la configuración SMTP en Configuración del sistema.',
            ], 422);
        }

        $recurrenceMessage = RecurrenceMessage::create([
            'pet_id' => $pet->id,
            'service_id' => $service->id,
            'whatsapp_template_id' => $template->id,
            'channel' => 'email',
            'email_address' => $resolved['to'],
            'message_body' => $resolved['message'],
            'sent_by_user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        return response()->json([
            'channel' => 'email',
            'sent_at' => $recurrenceMessage->sent_at,
        ]);
    }

    /**
     * @return array{0: ?Pet, 1: ?Service, 2: ?WhatsAppTemplate, 3: ?JsonResponse}
     */
    private function loadRecipient(string $key, int $templateId): array
    {
        [$petId, $serviceId] = array_map('intval', explode(':', $key));

        $pet = Pet::with('client.phones')->findOrFail($petId);
        $service = Service::findOrFail($serviceId);
        $template = WhatsAppTemplate::findOrFail($templateId);

        if (! $service->recurrence_days) {
            return [null, null, null, response()->json([
                'message' => 'Este servicio no tiene recurrencia configurada.',
            ], 422)];
        }

        return [$pet, $service, $template, null];
    }

    /**
     * @return array{wa_number: string, message: string}|JsonResponse
     */
    private function resolveMessage(Pet $pet, Service $service, WhatsAppTemplate $template): array|JsonResponse
    {
        $lastAt = $this->lastServiceDatesByPet($service)->get($pet->id);

        if (! $lastAt) {
            return response()->json([
                'message' => 'Esta mascota no tiene historial de este servicio.',
            ], 422);
        }

        $client = $pet->client;

        if ($client && ! $client->receives_service_reminders) {
            return response()->json([
                'message' => 'El cliente optó por no recibir recordatorios de servicio.',
            ], 422);
        }

        $rawPhone = $client ? PhoneNormalizer::bestPhoneFor($client) : null;
        $waNumber = PhoneNormalizer::toWhatsAppNumber($rawPhone);

        if (! $waNumber) {
            return response()->json([
                'message' => 'El cliente no tiene un teléfono válido de 10 dígitos (MX) registrado.',
            ], 422);
        }

        $dueDate = $lastAt->copy()->addDays($service->recurrence_days);
        $daysOverdue = (int) $dueDate->diffInDays(now());

        return [
            'wa_number' => $waNumber,
            'message' => TemplateResolver::resolveForRecurrence($template->body, $pet, $service, $lastAt, $daysOverdue),
        ];
    }

    /**
     * @return array{to: string, subject: string, message: string, client: Client}|JsonResponse
     */
    private function resolveEmailMessage(Pet $pet, Service $service, WhatsAppTemplate $template): array|JsonResponse
    {
        $lastAt = $this->lastServiceDatesByPet($service)->get($pet->id);

        if (! $lastAt) {
            return response()->json([
                'message' => 'Esta mascota no tiene historial de este servicio.',
            ], 422);
        }

        $client = $pet->client;

        if ($client && ! $client->receives_service_reminders) {
            return response()->json([
                'message' => 'El cliente optó por no recibir recordatorios de servicio.',
            ], 422);
        }

        if (! $client?->email) {
            return response()->json([
                'message' => 'El cliente no tiene correo electrónico registrado.',
            ], 422);
        }

        $dueDate = $lastAt->copy()->addDays($service->recurrence_days);
        $daysOverdue = (int) $dueDate->diffInDays(now());

        return [
            'to' => $client->email,
            'subject' => TemplateResolver::resolveForRecurrence($template->subject ?: $template->name, $pet, $service, $lastAt, $daysOverdue),
            'message' => TemplateResolver::resolveForRecurrence($template->body, $pet, $service, $lastAt, $daysOverdue),
            'client' => $client,
        ];
    }

    /**
     * Última fecha en que la mascota recibió el servicio, tomada de las citas SPA completadas
     * (`spa_bookings.status = 'completed'` + `spa_booking_services`). `executed_services`/
     * `executed_service_items` existen en el esquema pero ningún flujo real las llena hoy
     * (ver NT-020) — no son fuente de verdad utilizable.
     *
     * @return Collection<int, Carbon> última fecha por pet_id
     */
    private function lastServiceDatesByPet(Service $service): Collection
    {
        return DB::table('spa_booking_services')
            ->join('spa_bookings', 'spa_bookings.id', '=', 'spa_booking_services.spa_booking_id')
            ->where('spa_booking_services.service_id', $service->id)
            ->where('spa_bookings.status', 'completed')
            ->select('spa_bookings.pet_id', DB::raw('MAX(spa_bookings.scheduled_at) as last_at'))
            ->groupBy('spa_bookings.pet_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->pet_id => Carbon::parse($row->last_at)]);
    }
}
