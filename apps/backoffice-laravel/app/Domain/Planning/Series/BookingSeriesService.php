<?php

namespace App\Domain\Planning\Series;

use App\Domain\Planning\Services\OperatorAvailabilityChecker;
use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Models\NonWorkingDay;
use App\Models\SpaBooking;
use App\Models\SpaBookingSeries;
use App\Models\SpaBookingService;
use App\Support\SystemSettings\BusinessHours;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ZEUS-047: arma y materializa una serie de citas recurrentes.
 *
 * Cada fecha que dicta la regla se valida con lo mismo que una cita normal (día inhábil,
 * días y horario operativo de la sucursal, `validateSequentialAssignments` por línea, jaula) más la
 * mascota encimada consigo misma. Si la fecha no sirve, se recorre sola: primero más tarde ese
 * mismo día, después los días siguientes empezando por la hora original. Si en
 * SEARCH_DAYS días no hay lugar, la fecha queda omitida y registrada en la serie para revisión.
 *
 * `$template` (lo mismo que se guarda en `spa_booking_series.template`):
 *   pet_id, branch_id?, operator_id (responsable), notes?, resource_id?,
 *   lines: [{service_id, service_name, operator_id|null, duration_minutes, price, offset_minutes?}]
 */
class BookingSeriesService
{
    public const SEARCH_DAYS = 14;

    public const STEP_MINUTES = 30;

    public function __construct(
        private readonly OperatorAvailabilityChecker $availability,
        private readonly BusinessHours $businessHours,
        private readonly ResourceAllocationServiceInterface $resources,
    ) {}

    /**
     * Solo calcula — no escribe nada. Las fechas ya ubicadas de la propia serie cuentan como
     * ocupadas para las siguientes (aún no existen en BD).
     *
     * @return array<int, array{original_at: Carbon, scheduled_at: ?Carbon, reason: ?string}>
     */
    public function plan(array $template, RecurrenceRule $rule, Carbon $start, Carbon $endsOn): array
    {
        $claimed = [];
        $plan = [];

        foreach ($rule->occurrences($start, $endsOn) as $original) {
            [$slot, $reason] = $this->locate($template, $original, $claimed);

            if ($slot) {
                $claimed[] = [$slot, $slot->copy()->addMinutes($this->duration($template))];
            }

            $plan[] = ['original_at' => $original, 'scheduled_at' => $slot, 'reason' => $reason];
        }

        return $plan;
    }

    /**
     * Crea la serie y todas sus citas en una sola transacción. El plan se calcula aquí mismo,
     * contra el estado actual de la agenda — no se confía en una vista previa vieja.
     */
    public function create(array $template, RecurrenceRule $rule, Carbon $start, Carbon $endsOn, string $status = SpaBookingSeries::STATUS_PENDING_REVIEW): SpaBookingSeries
    {
        return DB::transaction(function () use ($template, $rule, $start, $endsOn, $status) {
            $series = SpaBookingSeries::create([
                'pet_id' => $template['pet_id'],
                'branch_id' => $template['branch_id'] ?? null,
                'created_by_user_id' => auth()->id(),
                'status' => $status,
                'rule' => $rule->toArray(),
                'template' => $template,
                'starts_at' => $start,
                'ends_on' => $endsOn->toDateString(),
                'notes' => $template['notes'] ?? null,
            ]);

            $skipped = [];
            foreach ($this->plan($template, $rule, $start, $endsOn) as $item) {
                if (! $item['scheduled_at']) {
                    $skipped[] = ['original_at' => $item['original_at']->toDateTimeString(), 'reason' => $item['reason']];

                    continue;
                }

                $this->materialize($series, $template, $item);
            }

            $series->update(['skipped_occurrences' => $skipped ?: null]);

            return $series;
        });
    }

    /** @return array{0: ?Carbon, 1: ?string} [hora final, motivo por el que no sirvió la original] */
    private function locate(array $template, Carbon $original, array $claimed): array
    {
        $reason = $this->rejectReason($template, $original, $claimed);
        if ($reason === null) {
            return [$original, null];
        }

        $branchId = $template['branch_id'] ?? null;
        $hours = $this->businessHours->for($branchId);
        [$openH, $openM] = array_map('intval', explode(':', $hours->openingTime()));
        [$closeH, $closeM] = array_map('intval', explode(':', $hours->closingTime()));

        for ($offset = 0; $offset <= self::SEARCH_DAYS; $offset++) {
            $day = $original->copy()->addDays($offset);
            if (! $hours->isOpenOn($day) || NonWorkingDay::covering($day, $branchId)) {
                continue;
            }

            $open = $day->copy()->setTime($openH, $openM);
            $close = $day->copy()->setTime($closeH, $closeM);

            // Mismo día: solo más tarde. Días siguientes: la hora original primero, luego el día completo.
            $candidates = [];
            if ($offset === 0) {
                for ($t = $original->copy()->addMinutes(self::STEP_MINUTES); $t->lte($close); $t->addMinutes(self::STEP_MINUTES)) {
                    $candidates[] = $t->copy();
                }
            } else {
                $candidates[] = $day->copy();
                for ($t = $open->copy(); $t->lte($close); $t->addMinutes(self::STEP_MINUTES)) {
                    if (! $t->eq($day)) {
                        $candidates[] = $t->copy();
                    }
                }
            }

            foreach ($candidates as $candidate) {
                if ($this->rejectReason($template, $candidate, $claimed) === null) {
                    return [$candidate, $reason];
                }
            }
        }

        return [null, $reason];
    }

    private function rejectReason(array $template, Carbon $start, array $claimed): ?string
    {
        if ($start->lte(now())) {
            return 'La fecha ya pasó.';
        }

        $branchId = $template['branch_id'] ?? null;
        $end = $start->copy()->addMinutes($this->duration($template));

        if ($day = NonWorkingDay::covering($start, $branchId)) {
            return "Día inhábil: {$day->reason}.";
        }

        if ($error = $this->businessHours->for($branchId)->rejectionFor($start)) {
            return $error;
        }

        foreach ($claimed as [$cs, $ce]) {
            if ($cs->lt($end) && $ce->gt($start)) {
                return 'Choca con otra cita de la misma serie.';
            }
        }

        $petBusy = SpaBooking::where('pet_id', $template['pet_id'])
            ->whereNotIn('status', [...SpaBooking::NOT_PERFORMED_STATUSES, 'completed'])
            ->where('scheduled_at', '<', $end)
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL COALESCE(duration_minutes, 0) MINUTE) > ?', [$start])
            ->exists();
        if ($petBusy) {
            return 'La mascota ya tiene otra cita en ese horario.';
        }

        if ($error = $this->availability->validateSequentialAssignments($start, $this->lines($template), false)) {
            return $error;
        }

        if (! empty($template['resource_id'])
            && ! $this->resources->resourceIsAvailable((int) $template['resource_id'], $start->toDateTimeString(), $end->toDateTimeString())) {
            return 'La jaula está ocupada en ese horario.';
        }

        return null;
    }

    /** @param array{original_at: Carbon, scheduled_at: Carbon, reason: ?string} $item */
    private function materialize(SpaBookingSeries $series, array $template, array $item): SpaBooking
    {
        $start = $item['scheduled_at'];
        $moved = ! $start->eq($item['original_at']);

        $booking = SpaBooking::create([
            'pet_id' => $template['pet_id'],
            'operator_id' => $template['operator_id'],
            'branch_id' => $template['branch_id'] ?? null,
            'created_by_user_id' => auth()->id(),
            'scheduled_at' => $start,
            'duration_minutes' => $this->duration($template),
            'status' => 'scheduled',
            'total_estimated_price' => collect($template['lines'])->sum(fn ($l) => (float) ($l['price'] ?? 0)),
            'notes' => $template['notes'] ?? null,
            'series_id' => $series->id,
            'series_original_at' => $moved ? $item['original_at'] : null,
            'series_move_reason' => $moved ? $item['reason'] : null,
        ]);

        $cursor = 0;
        foreach ($template['lines'] as $line) {
            $offset = isset($line['offset_minutes']) ? (int) $line['offset_minutes'] : $cursor;
            SpaBookingService::create([
                'spa_booking_id' => $booking->id,
                'service_id' => $line['service_id'],
                'current_price' => $line['price'] ?? 0,
                'operator_id' => $line['operator_id'] ?? null,
                'scheduled_offset_minutes' => $offset,
                'duration_minutes' => (int) $line['duration_minutes'],
            ]);
            $cursor = $offset + (int) $line['duration_minutes'];
        }

        if (! empty($template['resource_id'])) {
            try {
                $this->resources->assignResourceWindowToSource(
                    (int) $template['resource_id'],
                    $booking,
                    $booking->pet_id,
                    $start->toDateTimeString(),
                    $start->copy()->addMinutes($this->duration($template))->toDateTimeString(),
                    'reserved',
                    (int) config('backoffice.system.resource_cleaning_buffer_minutes', 15),
                );
            } catch (RuntimeException) {
                // Ya se validó libre; si la limpieza entre estancias la pisa, la cita queda sin
                // jaula y se señala para que la revisión la asigne a mano.
                $booking->update(['series_move_reason' => trim(($booking->series_move_reason ?? '').' Jaula sin asignar.')]);
            }
        }

        return $booking;
    }

    /** Fin más lejano de las líneas (offset + duración), igual que el alta móvil. */
    private function duration(array $template): int
    {
        $cursor = 0;
        $end = 0;
        foreach ($template['lines'] as $line) {
            $offset = isset($line['offset_minutes']) ? (int) $line['offset_minutes'] : $cursor;
            $cursor = $offset + (int) $line['duration_minutes'];
            $end = max($end, $cursor);
        }

        return $end;
    }

    /** @return array<int, array{operator_id: ?int, duration_minutes: int, service_name: string, offset_minutes?: int}> */
    private function lines(array $template): array
    {
        return array_map(fn ($l) => array_filter([
            'operator_id' => $l['operator_id'] ?? null,
            'duration_minutes' => (int) $l['duration_minutes'],
            'service_name' => (string) ($l['service_name'] ?? 'Servicio'),
            'offset_minutes' => $l['offset_minutes'] ?? null,
        ], fn ($v, $k) => $k !== 'offset_minutes' || $v !== null, ARRAY_FILTER_USE_BOTH), $template['lines']);
    }
}
