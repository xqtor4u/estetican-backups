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
     * Foto de disponibilidad de un operador para un día — para mostrarla en la UI de
     * agendado (no valida nada, solo describe).
     *
     * `window`: rango de labor de ese día (`['start' => '09:00', 'end' => '18:00']`), o
     * `['start' => null, 'end' => null]` si el operador tiene horario capturado pero no labora
     * ese día, o `null` si no tiene ningún horario capturado (trabaja a cualquier hora).
     * `busy`: tramos ya ocupados ese día (citas + vacaciones/permisos), ordenados.
     *
     * @return array{window: array{start: ?string, end: ?string}|null, busy: array<int, array{start: string, end: string, label: string}>}
     */
    public function daySummaryFor(int $operatorId, Carbon $day): array
    {
        $window = null;

        if (OperatorWeeklySchedule::where('operator_id', $operatorId)->exists()) {
            $daySchedule = OperatorWeeklySchedule::where('operator_id', $operatorId)
                ->where('day_of_week', $day->dayOfWeek)
                ->first();

            $window = $daySchedule
                ? ['start' => substr((string) $daySchedule->start_time, 0, 5), 'end' => substr((string) $daySchedule->end_time, 0, 5)]
                : ['start' => null, 'end' => null];
        }

        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        $busy = SpaBooking::where('operator_id', $operatorId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->orderBy('scheduled_at')
            ->get(['scheduled_at', 'duration_minutes'])
            ->map(fn ($b) => [
                'start' => $b->scheduled_at->format('H:i'),
                'end' => $b->scheduled_at->copy()->addMinutes((int) ($b->duration_minutes ?: 0))->format('H:i'),
                'label' => 'Cita',
            ])
            ->all();

        foreach (OperatorUnavailability::where('operator_id', $operatorId)->overlapping($dayStart, $dayEnd)->get() as $off) {
            $busy[] = [
                'start' => ($off->starts_at->lt($dayStart) ? $dayStart : $off->starts_at)->format('H:i'),
                'end' => ($off->ends_at->gt($dayEnd) ? $dayEnd : $off->ends_at)->format('H:i'),
                'label' => $off->reason ?: 'No disponible',
            ];
        }

        usort($busy, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return ['window' => $window, 'busy' => $busy];
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
     * Valida disponibilidad de un operador distinto por cada línea de servicio de una cita,
     * asumiendo que los servicios ocurren en secuencia sobre la misma mascota (la mascota está
     * ocupada toda la cita, pero cada operador solo está ocupado durante su propio segmento —
     * si el operador de la línea 2 empieza 30 min después del inicio de la cita, solo se valida
     * su disponibilidad a partir de ese minuto 30, no desde el inicio).
     *
     * $lines: arreglo ordenado de ['duration_minutes' => int, 'operator_id' => int,
     * 'service_name' => string]. Con una sola línea (o todas con el mismo operador), el único
     * segmento equivale a la cita completa — mismo resultado que validar un solo operador contra
     * toda la duración, así que este método reemplaza esa validación en vez de sumarse a ella.
     *
     * Devuelve el primer mensaje de error real encontrado, o null si todas las líneas pasan.
     */
    public function validateSequentialAssignments(Carbon $start, array $lines, bool $overrideSchedule, ?int $excludeBookingId = null): ?string
    {
        $cursor = $start->copy();

        foreach ($lines as $line) {
            $operatorId = $line['operator_id'];
            $durationMinutes = (int) $line['duration_minutes'];
            $serviceName = $line['service_name'];

            if ($this->hasConflict($operatorId, $cursor, $durationMinutes, $excludeBookingId)) {
                return "El operador asignado a \"{$serviceName}\" ya tiene una cita en ese horario.";
            }

            if (! $overrideSchedule && $this->isOutsideWorkingHours($operatorId, $cursor, $durationMinutes)) {
                return "El operador asignado a \"{$serviceName}\" no labora en el horario indicado.";
            }

            if ($this->hasTimeOff($operatorId, $cursor, $durationMinutes)) {
                return "El operador asignado a \"{$serviceName}\" no está disponible en ese periodo (vacaciones/permiso).";
            }

            $cursor->addMinutes(max($durationMinutes, 0));
        }

        return null;
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
