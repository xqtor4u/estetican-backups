<?php

namespace App\Domain\Planning\Services;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;

/**
 * Aplica una acción sobre una línea de servicio de una cita (iniciar / completar / "realizada" /
 * "no se realizó" / cancelar / reactivar / reasignar operador), con los mismos guardas y efectos
 * secundarios (recálculo del total a cobrar, promoción de la cita a `work_order` al arrancar una
 * línea) para web y API.
 *
 * Fuente única para `SpaBookingController::updateServiceLine` (web) y
 * `Api\BookingController::assignServiceProfessional` (móvil).
 */
class ServiceLineActionService
{
    /**
     * @param  array<string, mixed>  $data  claves ya validadas: operator_id, is_external,
     *                                      external_cost, current_price, mark_started,
     *                                      mark_completed, mark_realizada, mark_not_performed,
     *                                      not_performed_reason, mark_cancelled,
     *                                      cancellation_reason, mark_reactivate
     * @return string|null mensaje de error (transición inválida) o null si se aplicó
     */
    public function apply(SpaBooking $booking, SpaBookingService $line, array $data): ?string
    {
        $voided = $line->cancelled_at !== null || $line->not_performed_at !== null;

        if ((! empty($data['mark_started']) || ! empty($data['mark_completed']) || ! empty($data['mark_realizada'])) && $voided) {
            return 'No se puede iniciar ni terminar un servicio no realizado o cancelado. Reactívalo primero.';
        }
        if ((! empty($data['mark_not_performed']) || ! empty($data['mark_cancelled'])) && $line->completed_at) {
            return 'El servicio ya está completado.';
        }
        if (! empty($data['mark_completed']) && ! $line->started_at) {
            return 'No se puede completar un servicio que no ha iniciado.';
        }

        $fill = [];
        foreach (['operator_id', 'is_external', 'external_cost', 'current_price'] as $field) {
            if (array_key_exists($field, $data)) {
                $fill[$field] = $data[$field];
            }
        }

        // Hora del servidor, no la del cliente. Ambas banderas se ignoran si la línea ya
        // estaba en ese estado, para que reintentar la misma acción no mueva la hora real.
        if (! empty($data['mark_started']) && ! $line->started_at) {
            $fill['started_at'] = now();
        }
        if (! empty($data['mark_completed']) && ! $line->completed_at) {
            $fill['completed_at'] = now();
        }
        // "Realizada": da por hecho el servicio aunque no se haya tocado "Iniciar".
        if (! empty($data['mark_realizada'])) {
            if (! $line->started_at) {
                $fill['started_at'] = now();
            }
            if (! $line->completed_at) {
                $fill['completed_at'] = now();
            }
        }
        // "No se realizó" / "Cancelar": mutuamente excluyentes; ambas sacan la línea del total.
        if (! empty($data['mark_not_performed'])) {
            $fill['not_performed_at'] = $line->not_performed_at ?? now();
            $fill['not_performed_reason'] = $data['not_performed_reason'] ?? null;
            $fill['cancelled_at'] = null;
            $fill['cancellation_reason'] = null;
        }
        if (! empty($data['mark_cancelled'])) {
            $fill['cancelled_at'] = $line->cancelled_at ?? now();
            $fill['cancellation_reason'] = $data['cancellation_reason'] ?? null;
            $fill['not_performed_at'] = null;
            $fill['not_performed_reason'] = null;
        }
        if (! empty($data['mark_reactivate'])) {
            $fill['cancelled_at'] = null;
            $fill['cancellation_reason'] = null;
            $fill['not_performed_at'] = null;
            $fill['not_performed_reason'] = null;
        }

        $line->update($fill);

        $touchesTotal = array_key_exists('current_price', $fill)
            || array_key_exists('cancelled_at', $fill)
            || array_key_exists('not_performed_at', $fill);
        if ($touchesTotal) {
            $booking->update(['total_estimated_price' => $booking->services()->billable()->sum('current_price')]);
        }

        // Cancelar / no realizar / reactivar una línea cambia el "fin más lejano" de la cita
        // — se recalcula la duración a nivel cita para que la agenda no muestre tiempo
        // ocupado que ya se liberó (ni al revés). Solo cuenta líneas activas.
        if (array_key_exists('cancelled_at', $fill) || array_key_exists('not_performed_at', $fill)) {
            $active = $booking->services()
                ->whereNull('cancelled_at')
                ->whereNull('not_performed_at')
                ->with('service:id,duration_minutes')
                ->get();
            if ($active->isNotEmpty()) {
                $span = $active->map(fn ($s) => (int) ($s->scheduled_offset_minutes ?? 0)
                    + (int) ($s->duration_minutes ?? $s->service?->duration_minutes ?? 30))->max();
                $booking->update(['duration_minutes' => (int) $span]);
            }
        }

        // Arrancar una línea promueve toda la cita a "En proceso" si aún no lo estaba.
        if (array_key_exists('started_at', $fill) && $booking->status === 'scheduled') {
            $booking->update(['status' => 'work_order']);
            if (! $booking->order_folio) {
                try {
                    app(AccountingServiceInterface::class)->assignOrderFolio($booking);
                } catch (\Throwable) {
                    // No interrumpir el cambio de estado si falla la asignación del folio.
                }
            }
        }

        return null;
    }
}
