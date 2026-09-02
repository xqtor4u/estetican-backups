<?php

namespace App\Observers;

use App\Jobs\SyncBookingToGoogleJob;
use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;

/**
 * SYNC-079 — dispara la sincronización a Google Calendar en cuanto cambia la
 * agenda, sin esperar al barrido horario. Encola SyncBookingToGoogleJob (cola
 * `google-calendar`, drenada cada minuto por `schedule:run`) solo cuando el
 * cambio realmente afecta al evento de Google.
 */
class SpaBookingObserver
{
    /**
     * Columnas de `spa_bookings` que alimentan el evento de Google
     * (ver GoogleCalendarSyncService::buildEvent). Un cambio de
     * `total_estimated_price` u otros campos internos no toca el evento.
     */
    private const EVENT_FIELDS = [
        'scheduled_at',
        'duration_minutes',
        'status',
        'operator_id',
        'pet_id',
        'notes',
        'order_folio',
    ];

    public function __construct(private readonly SystemSettings $settings) {}

    public function saved(SpaBooking $booking): void
    {
        if (! $this->syncEnabled()) {
            return;
        }

        // Alta: siempre. Edición: solo si cambió un campo que alimenta el evento.
        if (! $booking->wasRecentlyCreated && ! $booking->wasChanged(self::EVENT_FIELDS)) {
            return;
        }

        // delay + ShouldBeUnique: varios save() de la misma cita en un request
        // (status, duración, precio) colapsan en un solo job que relee el estado final.
        SyncBookingToGoogleJob::dispatch($booking->id)->delay(now()->addSeconds(10));
    }

    private function syncEnabled(): bool
    {
        try {
            return (bool) $this->settings->all()['google_calendar_sync_enabled'];
        } catch (\Throwable) {
            // Tabla de configuración aún no existe (antes de la primera migración).
            return false;
        }
    }
}
