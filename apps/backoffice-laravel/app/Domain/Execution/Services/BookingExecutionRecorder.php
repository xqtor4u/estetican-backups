<?php

namespace App\Domain\Execution\Services;

use App\Models\ExecutedService;
use App\Models\SpaBooking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * B4 (27/09/2026): registro histórico inmutable de lo que se ejecutó en una cita
 * (`executed_services` + `executed_service_items`). Se escribe al pasar la cita a `completed`
 * (SpaBookingObserver) — un solo lugar para web, cobro móvil y edición. Editar la cita después
 * no lo toca: las líneas de `spa_booking_services` se pueden borrar y recrear, este registro no.
 * Si la cita se reabre y se vuelve a completar, se reemplaza por la nueva ejecución.
 */
class BookingExecutionRecorder
{
    public function record(SpaBooking $booking, ?Carbon $executedAt = null): ExecutedService
    {
        $booking->loadMissing(['services.service', 'items', 'quotes']);

        return DB::transaction(function () use ($booking, $executedAt): ExecutedService {
            ExecutedService::where('spa_booking_id', $booking->id)->delete();

            $lines = $booking->services->whereNull('cancelled_at')->whereNull('not_performed_at');

            $executed = ExecutedService::create([
                'spa_booking_id' => $booking->id,
                'pet_id' => $booking->pet_id,
                'operator_id' => $booking->operator_id,
                'final_price' => $booking->chargesTotal(),
                'service_summary' => $lines->map(fn ($l) => $l->service_name_snapshot ?? $l->service?->name)->filter()->implode(', ') ?: null,
                'notes' => $booking->notes,
                'executed_at' => $executedAt ?? now(),
            ]);

            foreach ($lines as $line) {
                $executed->items()->create([
                    'service_id' => $line->service_id,
                    'service_name_snapshot' => $line->service_name_snapshot ?? $line->service?->name ?? 'Servicio #'.$line->service_id,
                    'service_description_snapshot' => $line->service?->description,
                    'service_type_snapshot' => $line->service?->type,
                    'charged_price' => (float) $line->current_price,
                    'duration_minutes_snapshot' => $line->duration_minutes ?? $line->service?->duration_minutes,
                    'operator_id' => $line->operator_id ?? $booking->operator_id,
                    'is_external' => (bool) ($line->is_external ?? false),
                ]);
            }

            return $executed;
        });
    }
}
