<?php

namespace App\Observers;

use App\Jobs\SyncBookingToGoogleJob;
use App\Models\SpaBookingService;
use App\Support\SystemSettings\SystemSettings;

/**
 * SYNC-079 — las líneas de servicio alimentan el título del evento de Google
 * (nombres de los servicios). Agregar/quitar/cambiar una línea no toca
 * `spa_bookings.updated_at`, así que ni el observer de la cita ni el barrido
 * (ambos keyean por `updated_at`) lo detectarían. Este observer encola el
 * mismo job para la cita padre.
 */
class SpaBookingServiceObserver
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function saved(SpaBookingService $line): void
    {
        $this->queueParent($line);
    }

    public function deleted(SpaBookingService $line): void
    {
        $this->queueParent($line);
    }

    private function queueParent(SpaBookingService $line): void
    {
        if (! $line->spa_booking_id || ! $this->syncEnabled()) {
            return;
        }

        SyncBookingToGoogleJob::dispatch($line->spa_booking_id)->delay(now()->addSeconds(10));
    }

    private function syncEnabled(): bool
    {
        try {
            return (bool) $this->settings->all()['google_calendar_sync_enabled'];
        } catch (\Throwable) {
            return false;
        }
    }
}
