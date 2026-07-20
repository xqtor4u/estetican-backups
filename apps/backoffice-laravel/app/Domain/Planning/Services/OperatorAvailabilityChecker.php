<?php

namespace App\Domain\Planning\Services;

use App\Models\OperatorUnavailability;
use App\Models\OperatorWeeklySchedule;
use App\Models\SpaBooking;
use Carbon\Carbon;

class OperatorAvailabilityChecker
{
    /**
     * Alcance intencional: solo SpaBooking. `operator_id` no existe en HotelReservation.
     */
    public function hasConflict(int $operatorId, Carbon $start, int $durationMinutes, ?int $excludeBookingId = null): bool
    {
        $end = $start->copy()->addMinutes(max($durationMinutes, 0));

        return SpaBooking::where('operator_id', $operatorId)
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('scheduled_at', '<', $end)
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL COALESCE(duration_minutes, 0) MINUTE) > ?', [$start])
            ->exists();
    }

    /**
     * Horario semanal opt-in: si el operador no tiene ninguna fila capturada en
     * `operator_weekly_schedules`, no hay restricción (compatibilidad con operadores
     * existentes que nunca configuraron horario). Si tiene al menos una fila, el día
     * agendado debe existir y la hora debe caer dentro del rango de ese día.
     */
    public function isOutsideWorkingHours(int $operatorId, Carbon $start, int $durationMinutes): bool
    {
        $hasAnySchedule = OperatorWeeklySchedule::where('operator_id', $operatorId)->exists();

        if (! $hasAnySchedule) {
            return false;
        }

        $end = $start->copy()->addMinutes(max($durationMinutes, 0));

        $daySchedule = OperatorWeeklySchedule::where('operator_id', $operatorId)
            ->where('day_of_week', $start->dayOfWeek)
            ->first();

        if (! $daySchedule) {
            return true;
        }

        return $start->format('H:i:s') < $daySchedule->start_time
            || $end->format('H:i:s') > $daySchedule->end_time;
    }

    /**
     * Bloqueos de no-disponibilidad (vacaciones/permisos): no son opt-in, si existe
     * una fila que se traslapa con el rango solicitado, siempre bloquea.
     */
    public function hasTimeOff(int $operatorId, Carbon $start, int $durationMinutes): bool
    {
        $end = $start->copy()->addMinutes(max($durationMinutes, 0));

        return OperatorUnavailability::where('operator_id', $operatorId)
            ->overlapping($start, $end)
            ->exists();
    }
}
