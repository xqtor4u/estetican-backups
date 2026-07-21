<?php

namespace App\Domain\Planning\Services;

use App\Models\OperatorUnavailability;
use App\Models\OperatorWeeklySchedule;
use App\Models\SpaBooking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * Fuente única de lectura de bloqueos para pintar agendas (día/semana/mes, web y móvil).
     * A diferencia de hasTimeOff() (que solo bloquea al guardar), este método expone las
     * ventanas reales para mostrarlas visualmente. $operatorId null = todos los operadores.
     */
    public function unavailabilityWindows(Carbon $rangeStart, Carbon $rangeEnd, ?int $operatorId = null): Collection
    {
        return OperatorUnavailability::query()
            ->when($operatorId, fn ($q) => $q->where('operator_id', $operatorId))
            ->overlapping($rangeStart, $rangeEnd)
            ->with('operator:id,first_name,apellido_paterno,apellido_materno,name')
            ->orderBy('starts_at')
            ->get();
    }
}
