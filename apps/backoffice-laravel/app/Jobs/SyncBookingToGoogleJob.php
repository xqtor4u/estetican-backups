<?php

namespace App\Jobs;

use App\Domain\GoogleCalendar\Contracts\GoogleCalendarSyncServiceInterface;
use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * SYNC-079 — empuja UNA cita al calendario de Google del operador en cuanto la
 * agenda cambia. Lo encolan SpaBookingObserver / SpaBookingServiceObserver.
 *
 * Complementa el barrido horario `calendario:sincronizar-google`, que sigue
 * haciendo el aprovisionamiento de calendarios (crear/compartir) y la
 * reconciliación de lo que este job no alcance (worker caído, error de Google,
 * escritura directa a BD).
 *
 * Se pasa el id, no el modelo: una cita suele recibir varios `save()` en un
 * mismo request (status, duración, precio) y ShouldBeUnique + el delay corto los
 * colapsan en un solo job que relee el estado final.
 */
class SyncBookingToGoogleJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** @var int */
    public $tries = 3;

    /** @var array<int, int> */
    public $backoff = [30, 120];

    /** Ventana de deduplicación: varios saves de la misma cita en segundos → un job. */
    public int $uniqueFor = 120;

    public function __construct(public int $bookingId)
    {
        $this->onQueue('google-calendar');
    }

    public function uniqueId(): string
    {
        return (string) $this->bookingId;
    }

    public function handle(GoogleCalendarSyncServiceInterface $sync, SystemSettings $settings): void
    {
        if (! $settings->all()['google_calendar_sync_enabled']) {
            return;
        }

        $booking = SpaBooking::query()
            ->with(['pet.client', 'operator', 'services.service'])
            ->find($this->bookingId);

        // Cita borrada de raíz (no es un flujo real hoy — cancelar es un cambio de
        // status). El barrido no re-borraría un evento huérfano así; queda como
        // limitación conocida, igual que en el cron previo.
        if (! $booking) {
            return;
        }

        // Ya sincronizada después de su último cambio: carrera con el barrido u
        // otro job de la misma cita. Mismo criterio que la query del cron
        // (`updated_at > google_synced_at` ⇒ pendiente).
        if ($booking->google_synced_at !== null && $booking->google_synced_at >= $booking->updated_at) {
            return;
        }

        if (in_array($booking->status, ['cancelled', 'no_show'], true)) {
            $sync->deleteBookingEvent($booking);

            return;
        }

        if (in_array($booking->status, ['scheduled', 'work_order', 'completed'], true)) {
            $sync->upsertBookingEvent($booking);
        }
    }
}
