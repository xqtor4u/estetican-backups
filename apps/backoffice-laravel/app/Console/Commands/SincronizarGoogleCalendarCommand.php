<?php

namespace App\Console\Commands;

use App\Domain\GoogleCalendar\Contracts\GoogleCalendarSyncServiceInterface;
use App\Models\Operator;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Console\Command;

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
            ->get();

        $toDelete = SpaBooking::whereIn('operator_id', $operatorIds)
            ->whereIn('status', ['cancelled', 'no_show'])
            ->whereNotNull('google_event_id')
            ->get();

        $synced = 0;
        $deleted = 0;

        foreach ($toUpsert as $booking) {
            if ($isDry) {
                $this->line("[dry-run] sincronizaría cita #{$booking->id} ({$booking->scheduled_at->format('d/m/Y H:i')})");
                $synced++;

                continue;
            }

            $this->sync->upsertBookingEvent($booking);
            $synced++;
        }

        foreach ($toDelete as $booking) {
            if ($isDry) {
                $this->line("[dry-run] borraría el evento de la cita cancelada #{$booking->id}");
                $deleted++;

                continue;
            }

            $this->sync->deleteBookingEvent($booking);
            $deleted++;
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
}
