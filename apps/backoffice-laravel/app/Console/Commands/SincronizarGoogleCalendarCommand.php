<?php

namespace App\Console\Commands;

use App\Domain\GoogleCalendar\Contracts\GoogleCalendarSyncServiceInterface;
use App\Mail\GoogleCalendarUpdatedMail;
use App\Models\Operator;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SincronizarGoogleCalendarCommand extends Command
{
    protected $signature = 'calendario:sincronizar-google {--dry-run : Solo muestra qué sincronizaría, sin llamar a la API de Google}';

    protected $description = 'Sincroniza (un solo sentido, EstetiCAN → Google) las citas SPA de los operadores con la agenda compartida activada hacia su calendario de Google.';

    public function __construct(
        private readonly GoogleCalendarSyncServiceInterface $sync,
        private readonly SystemSettings $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDry = (bool) $this->option('dry-run');
        $all = $this->settings->all();

        if (! $all['google_calendar_sync_enabled']) {
            $this->info('google_calendar_sync_enabled está apagado — nada que hacer.');

            return self::SUCCESS;
        }

        $operators = Operator::where('google_calendar_share_enabled', true)
            ->whereNotNull('google_personal_email')
            ->get();

        foreach ($operators as $operator) {
            if ($isDry) {
                $this->line("[dry-run] operador #{$operator->id} ({$operator->full_name}): aseguraría calendario y lo compartiría con {$operator->google_personal_email}");

                continue;
            }

            $calendarId = $this->sync->ensureCalendarForOperator($operator);

            if ($calendarId && ! $operator->google_calendar_shared_at) {
                if ($this->sync->shareCalendarWithEmail($calendarId, $operator->google_personal_email)) {
                    $operator->forceFill(['google_calendar_shared_at' => now()])->saveQuietly();
                }
            }
        }

        $this->syncViewers($isDry);

        $operatorIds = $operators->pluck('id');

        $toUpsert = SpaBooking::whereIn('operator_id', $operatorIds)
            ->whereIn('status', ['scheduled', 'work_order', 'completed'])
            ->where('scheduled_at', '>=', now()->subDay())
            ->where(function ($q) {
                $q->whereNull('google_synced_at')
                    ->orWhereColumn('updated_at', '>', 'google_synced_at');
            })
            ->with(['pet', 'operator', 'services.service'])
            ->get();

        $toDelete = SpaBooking::whereIn('operator_id', $operatorIds)
            ->whereIn('status', ['cancelled', 'no_show'])
            ->whereNotNull('google_event_id')
            ->with(['pet', 'operator', 'services.service'])
            ->get();

        $synced = 0;
        $deleted = 0;

        // operator_id => [ ['type' => nueva|actualizada|cancelada, 'booking' => SpaBooking], ... ]
        // Alimenta el aviso por correo (una vez por corrida con cambios) de los usuarios
        // con google_calendar_notify_email activado.
        $changesByOperator = [];

        foreach ($toUpsert as $booking) {
            if ($isDry) {
                $this->line("[dry-run] sincronizaría cita #{$booking->id} ({$booking->scheduled_at->format('d/m/Y H:i')})");
                $synced++;

                continue;
            }

            $wasNew = $booking->google_synced_at === null;
            $this->sync->upsertBookingEvent($booking);
            $changesByOperator[$booking->operator_id][] = [
                'type' => $wasNew ? 'nueva' : 'actualizada',
                'booking' => $booking,
            ];
            $synced++;
        }

        foreach ($toDelete as $booking) {
            if ($isDry) {
                $this->line("[dry-run] borraría el evento de la cita cancelada #{$booking->id}");
                $deleted++;

                continue;
            }

            $this->sync->deleteBookingEvent($booking);
            $changesByOperator[$booking->operator_id][] = ['type' => 'cancelada', 'booking' => $booking];
            $deleted++;
        }

        if (! $isDry && $changesByOperator !== []) {
            $this->notifyWatchers($changesByOperator);
        }

        $prefix = $isDry ? '[dry-run] ' : '';
        $this->info("{$prefix}Google Calendar: {$synced} citas sincronizadas, {$deleted} eventos borrados, {$operators->count()} operadores con agenda compartida.");

        return self::SUCCESS;
    }

    /**
     * Comparte calendarios de operador con usuarios (login) que cargaron su propio email
     * personal de Google — independiente de si ese usuario es también un operador. Con
     * visibilidad 'all' ve todos los calendarios que existan hoy; con 'personal' solo el de
     * su propio operador vinculado (operator_id), si tiene uno con calendario ya creado.
     * No hace falta rastrear "ya compartido": la API de Calendar es idempotente ante un ACL
     * insert repetido con el mismo email+rol (confirmado en vivo), así que se llama cada
     * corrida sin necesitar una columna de estado nueva.
     */
    private function syncViewers(bool $isDry): void
    {
        $viewers = User::whereNotNull('google_personal_email')->with('operator')->get();

        if ($viewers->isEmpty()) {
            return;
        }

        $allCalendarIds = null;

        foreach ($viewers as $viewer) {
            $calendarIds = $viewer->google_calendar_visibility === 'all'
                ? ($allCalendarIds ??= Operator::whereNotNull('google_calendar_id')->pluck('google_calendar_id'))
                : collect([$viewer->operator?->google_calendar_id])->filter();

            foreach ($calendarIds as $calendarId) {
                if ($isDry) {
                    $this->line("[dry-run] usuario #{$viewer->id} ({$viewer->name}, {$viewer->google_calendar_visibility}): compartiría {$calendarId} con {$viewer->google_personal_email}");

                    continue;
                }

                $this->sync->shareCalendarWithEmail($calendarId, $viewer->google_personal_email);
            }
        }
    }

    /**
     * Manda un correo "tu calendario se actualizó" a los usuarios que activaron
     * `google_calendar_notify_email`, con los cambios que a ellos les corresponden:
     * visibilidad 'all' ve todos; 'personal' solo los de su operador vinculado. Mismo
     * criterio de alcance que syncViewers(). Se manda al email personal de Google del
     * usuario, o al de acceso si no cargó uno.
     *
     * @param  array<int, array<int, array{type: string, booking: SpaBooking}>>  $changesByOperator
     */
    private function notifyWatchers(array $changesByOperator): void
    {
        $recipients = User::where('google_calendar_notify_email', true)
            ->with('operator')
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        // ApplySystemSettings (que empuja el SMTP configurado en SystemSettings a config())
        // solo corre en requests HTTP — este comando vive en el cron, así que hay que
        // aplicar esos overrides a mano antes de enviar.
        $overrides = $this->settings->configOverrides();
        if ($overrides !== []) {
            config($overrides);
        }

        $businessName = $this->settings->all()['brand_business_name'] ?? 'EstetiCAN';
        $appUrl = (string) config('app.url');

        foreach ($recipients as $user) {
            $email = $user->google_personal_email ?: $user->email;

            if (! $email) {
                continue;
            }

            $relevant = $user->google_calendar_visibility === 'all'
                ? collect($changesByOperator)->flatten(1)
                : collect($changesByOperator[$user->operator?->id] ?? []);

            if ($relevant->isEmpty()) {
                continue;
            }

            $rows = $relevant->map(fn ($c) => $this->summarizeChange($c['type'], $c['booking']))->values()->all();

            Mail::to($email)->send(new GoogleCalendarUpdatedMail(
                recipientName: $user->first_name ?: $user->name,
                changes: $rows,
                businessName: $businessName,
                appUrl: $appUrl,
            ));

            $this->line("aviso de cambios de calendario enviado a {$email} (".count($rows).' cambios)');
        }
    }

    /**
     * @return array{type: string, pet: string, services: string, operator: string, when: string}
     */
    private function summarizeChange(string $type, SpaBooking $booking): array
    {
        $services = $booking->services->pluck('service.name')->filter()->implode(', ');

        return [
            'type' => $type,
            'pet' => $booking->pet->name ?? 'Mascota',
            'services' => $services !== '' ? $services : '—',
            'operator' => $booking->operator?->full_name ?? '—',
            'when' => $booking->scheduled_at?->format('d/m/Y H:i') ?? '—',
        ];
    }
}
