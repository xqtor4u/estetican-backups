<?php

namespace App\Observers;

use App\Jobs\SyncBookingToGoogleJob;
use App\Models\Service;
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

    /**
     * SYNC-105 (portado desde Zeus/ZEUS-037): congela el nombre del servicio al momento de crear
     * la línea — hay 3+ puntos de creación distintos (alta directa web/móvil, sincronizar
     * servicios al editar, aceptar presupuesto) y todos deben quedar protegidos por igual sin
     * repetir la lógica en cada uno. Solo rellena si el creador no lo mandó ya explícito (p. ej.
     * `QuoteService` manda el snapshot que ya traía el `QuoteItem` desde que se cotizó).
     */
    public function creating(SpaBookingService $line): void
    {
        if (! $line->service_name_snapshot && $line->service_id) {
            $line->service_name_snapshot = Service::find($line->service_id)?->name;
        }
    }

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
