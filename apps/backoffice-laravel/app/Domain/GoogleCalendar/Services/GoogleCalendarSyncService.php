<?php

namespace App\Domain\GoogleCalendar\Services;

use App\Domain\GoogleCalendar\Contracts\GoogleCalendarSyncServiceInterface;
use App\Models\Operator;
use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use App\Support\WhatsApp\PhoneNormalizer;
use Google\Client as GoogleClient;
use Google\Service\Calendar\AclRule;
use Google\Service\Calendar as GoogleCalendarApi;
use Google\Service\Calendar\Calendar as GoogleCalendarResource;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincronización de un solo sentido (EstetiCAN → Google), nunca al revés — editar el
 * evento en Google no debe tocar la cita real. Cada llamada a la API queda envuelta en
 * try/catch: una falla de Google nunca debe frenar el resto del lote ni el flujo real
 * de agendar (que ni siquiera pasa por esta clase, ver SincronizarGoogleCalendarCommand).
 *
 * google_event_id/google_synced_at en SpaBooking (y google_calendar_id/google_calendar_shared_at
 * en Operator) se escriben con forceFill()->saveQuietly() a propósito: son bookkeeping interno
 * de esta sincronización, no datos de negocio — no deben aparecer en el activity log de citas
 * ni requieren estar en el #[Fillable] del modelo.
 */
class GoogleCalendarSyncService implements GoogleCalendarSyncServiceInterface
{
    private const REMINDER_MINUTES_BEFORE = 15;

    public function __construct(private SystemSettings $settings) {}

    public function ensureCalendarForOperator(Operator $operator): ?string
    {
        if ($operator->google_calendar_id) {
            return $operator->google_calendar_id;
        }

        $api = $this->client();

        if (! $api) {
            return null;
        }

        try {
            $calendar = new GoogleCalendarResource([
                'summary' => "EstetiCAN — {$operator->full_name}",
                'timeZone' => $this->timezone(),
            ]);

            $created = $api->calendars->insert($calendar);

            $operator->forceFill(['google_calendar_id' => $created->getId()])->saveQuietly();

            return $created->getId();
        } catch (Throwable $e) {
            Log::error('GoogleCalendar: fallo al crear calendario del operador.', [
                'operator_id' => $operator->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function shareCalendarWithEmail(string $calendarId, string $email): bool
    {
        $api = $this->client();

        if (! $api) {
            return false;
        }

        try {
            // sendNotifications=false: no queremos que Google mande el correo
            // "se compartió un calendario contigo" en cada corrida del cron (el ACL
            // insert es idempotente, pero el default de la API es notificar). Los
            // avisos de cambios de eventos posteriores los controla el destinatario
            // en la configuración de su propio Google Calendar — EstetiCAN no puede
            // apagarlos desde acá.
            $api->acl->insert($calendarId, new AclRule([
                'role' => 'reader',
                'scope' => ['type' => 'user', 'value' => $email],
            ]), ['sendNotifications' => false]);

            return true;
        } catch (Throwable $e) {
            Log::error('GoogleCalendar: fallo al compartir el calendario.', [
                'calendar_id' => $calendarId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function upsertBookingEvent(SpaBooking $booking): void
    {
        $operator = $booking->operator;

        if (! $operator || ! $operator->google_calendar_id) {
            return;
        }

        $api = $this->client();

        if (! $api) {
            return;
        }

        $event = $this->buildEvent($booking);

        try {
            if ($booking->google_event_id) {
                $api->events->update($operator->google_calendar_id, $booking->google_event_id, $event);
            } else {
                $created = $api->events->insert($operator->google_calendar_id, $event);
                $booking->forceFill(['google_event_id' => $created->getId()])->saveQuietly();
            }

            $booking->forceFill(['google_synced_at' => now()])->saveQuietly();
        } catch (Throwable $e) {
            Log::error('GoogleCalendar: fallo al sincronizar la cita.', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleteBookingEvent(SpaBooking $booking): void
    {
        $operator = $booking->operator;

        if (! $operator || ! $operator->google_calendar_id || ! $booking->google_event_id) {
            return;
        }

        $api = $this->client();

        if (! $api) {
            return;
        }

        try {
            $api->events->delete($operator->google_calendar_id, $booking->google_event_id);
        } catch (Throwable $e) {
            // Puede ser que el evento ya no exista del lado de Google (410/404) — no es
            // un fallo real que bloquee nada, solo se deja registro.
            Log::warning('GoogleCalendar: no se pudo borrar el evento (puede que ya no exista).', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        $booking->forceFill(['google_event_id' => null, 'google_synced_at' => now()])->saveQuietly();
    }

    private function buildEvent(SpaBooking $booking): GoogleEvent
    {
        $pet = $booking->pet;
        $client = $pet?->client;

        $serviceNames = $booking->services->pluck('service.name')->filter()->implode(', ');
        $title = trim(($pet->name ?? 'Mascota').($serviceNames ? " — {$serviceNames}" : ''));

        $description = implode("\n", array_filter([
            $client ? "Cliente: {$client->full_name}" : null,
            $client ? 'Tel: '.(PhoneNormalizer::bestPhoneFor($client) ?? 'sin teléfono') : null,
            $booking->order_folio ? "Folio: {$booking->order_folio}" : null,
            $booking->notes ? "Notas: {$booking->notes}" : null,
        ]));

        $timezone = $this->timezone();
        $start = $booking->scheduled_at->copy();
        $end = $start->copy()->addMinutes($booking->duration_minutes ?: 30);

        return new GoogleEvent([
            'summary' => $title,
            'description' => $description,
            'start' => new EventDateTime(['dateTime' => $start->toRfc3339String(), 'timeZone' => $timezone]),
            'end' => new EventDateTime(['dateTime' => $end->toRfc3339String(), 'timeZone' => $timezone]),
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => self::REMINDER_MINUTES_BEFORE],
                ],
            ],
        ]);
    }

    private function timezone(): string
    {
        return (string) ($this->settings->all()['system_timezone'] ?? 'America/Mexico_City');
    }

    // protected (no private) para que un test pueda inyectar un cliente falso y verificar
    // los optParams que se le pasan a la API de Google (p. ej. sendNotifications=false).
    protected function client(): ?GoogleCalendarApi
    {
        $path = config('services.google_calendar.credentials_path');

        if (! $path || ! is_file($path)) {
            Log::warning('GoogleCalendar: GOOGLE_CALENDAR_CREDENTIALS_PATH no configurado o el archivo no existe — sincronización inactiva.');

            return null;
        }

        try {
            $client = new GoogleClient;
            $client->setAuthConfig($path);
            $client->setScopes([GoogleCalendarApi::CALENDAR]);
            $client->setApplicationName('EstetiCAN');

            return new GoogleCalendarApi($client);
        } catch (Throwable $e) {
            Log::error('GoogleCalendar: no se pudo inicializar el cliente de la API.', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
