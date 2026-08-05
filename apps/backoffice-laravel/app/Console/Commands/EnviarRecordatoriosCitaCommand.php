<?php

namespace App\Console\Commands;

use App\Domain\WhatsAppMessaging\Contracts\WhatsAppSenderInterface;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use App\Support\WhatsApp\PhoneNormalizer;
use Illuminate\Console\Command;

class EnviarRecordatoriosCitaCommand extends Command
{
    protected $signature = 'whatsapp:enviar-recordatorios-cita {--dry-run : Solo muestra qué mandaría, sin registrar ni llamar al proveedor}';

    protected $description = 'Envía recordatorios automáticos de WhatsApp para citas SPA próximas, a whatsapp_reminder_hours_before horas de distancia.';

    public function __construct(
        private readonly WhatsAppSenderInterface $sender,
        private readonly SystemSettings $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDry = (bool) $this->option('dry-run');
        $all = $this->settings->all();

        if (! $all['whatsapp_messaging_enabled']) {
            $this->info('whatsapp_messaging_enabled está apagado — nada que hacer.');

            return self::SUCCESS;
        }

        $templateName = $all['whatsapp_messaging_template_name'] ?? null;

        if (! $templateName) {
            $this->warn('whatsapp_messaging_template_name no está configurado — nada que hacer.');

            return self::SUCCESS;
        }

        $languageCode = $all['whatsapp_messaging_template_language'] ?? 'es_MX';
        $hoursBefore = (int) ($all['whatsapp_reminder_hours_before'] ?? 24);

        $candidates = SpaBooking::whereIn('status', ['scheduled', 'work_order'])
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addHours($hoursBefore))
            ->whereDoesntHave('messages', fn ($q) => $q->where('trigger', 'automatic_reminder'))
            ->with(['pet.client.phones', 'services.service'])
            ->orderBy('scheduled_at')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($candidates as $booking) {
            $client = $booking->pet?->client;

            if (! $client || ! $client->receives_service_reminders) {
                $skipped++;

                continue;
            }

            $waNumber = PhoneNormalizer::toWhatsAppNumber(PhoneNormalizer::bestPhoneFor($client));

            if (! $waNumber) {
                $skipped++;

                continue;
            }

            $parameters = $this->buildParameters($booking, $client);

            if ($isDry) {
                $this->line("[dry-run] {$client->full_name} ({$waNumber}) — cita #{$booking->id} el {$booking->scheduled_at->format('d/m/Y H:i')}");
                $sent++;

                continue;
            }

            $result = $this->sender->sendTemplate($waNumber, $templateName, $languageCode, $parameters);

            if ($result['status'] === 'sent') {
                BookingMessage::create([
                    'spa_booking_id' => $booking->id,
                    'channel' => 'whatsapp',
                    'trigger' => 'automatic_reminder',
                    'phone_number' => $waNumber,
                    'message_body' => implode(' | ', $parameters),
                    'provider_message_id' => $result['provider_message_id'],
                    'sent_at' => now(),
                ]);
                $sent++;
            } else {
                $this->warn("Cita #{$booking->id}: {$result['error']}");
                $skipped++;
            }
        }

        $prefix = $isDry ? '[dry-run] ' : '';
        $verb = $isDry ? 'se habrían enviado' : 'enviados';
        $this->info("{$prefix}Recordatorios: {$sent} {$verb}, {$skipped} omitidos, {$candidates->count()} candidatos totales.");

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function buildParameters(SpaBooking $booking, Client $client): array
    {
        $dateFormat = (string) config('backoffice.system.date_format', 'd/m/Y');
        $timeFormat = config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A';

        return [
            $client->full_name ?: 'Cliente',
            $booking->pet?->name ?: 'tu mascota',
            $booking->services->pluck('service.name')->filter()->implode(', ') ?: 'servicio agendado',
            $booking->scheduled_at->format($dateFormat),
            $booking->scheduled_at->format($timeFormat),
        ];
    }
}
