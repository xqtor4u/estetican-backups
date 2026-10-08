<?php

namespace App\Domain\Planning\Series;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * ZEUS-047: regla de repetición de una serie de citas.
 *
 * - `every_n_days`: cada `interval_days` días a la misma hora (7, 15, 30, personalizado).
 * - `monthly_weekday`: el `week_of_month`-ésimo `weekday` de cada mes ("1er lunes a las 12:00");
 *   `week_of_month` = -1 es "el último". Evita que una cita mensual caiga en domingo.
 *
 * La hora de cada fecha es la de la primera cita de la serie.
 */
final class RecurrenceRule
{
    public const EVERY_N_DAYS = 'every_n_days';

    public const MONTHLY_WEEKDAY = 'monthly_weekday';

    /** Tope de citas que genera una serie — 1 año semanal cabe holgado. */
    public const MAX_OCCURRENCES = 60;

    private const WEEKDAY_NAMES = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    private const ORDINALS = [1 => '1er', 2 => '2º', 3 => '3er', 4 => '4º', -1 => 'último'];

    private function __construct(
        public readonly string $type,
        public readonly ?int $intervalDays = null,
        public readonly ?int $weekOfMonth = null,
        public readonly ?int $weekday = null,
    ) {}

    public static function everyNDays(int $days): self
    {
        if ($days < 1 || $days > 365) {
            throw new InvalidArgumentException('La frecuencia debe ser de 1 a 365 días.');
        }

        return new self(self::EVERY_N_DAYS, intervalDays: $days);
    }

    /** @param int $weekday 0 = domingo … 6 = sábado (Carbon::dayOfWeek). */
    public static function monthlyWeekday(int $weekOfMonth, int $weekday): self
    {
        if (! in_array($weekOfMonth, [1, 2, 3, 4, -1], true) || $weekday < 0 || $weekday > 6) {
            throw new InvalidArgumentException('Regla mensual inválida.');
        }

        return new self(self::MONTHLY_WEEKDAY, weekOfMonth: $weekOfMonth, weekday: $weekday);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return match ($data['type'] ?? null) {
            self::EVERY_N_DAYS => self::everyNDays((int) ($data['interval_days'] ?? 0)),
            self::MONTHLY_WEEKDAY => self::monthlyWeekday((int) ($data['week_of_month'] ?? 0), (int) ($data['weekday'] ?? -1)),
            default => throw new InvalidArgumentException('Tipo de repetición desconocido.'),
        };
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return $this->type === self::EVERY_N_DAYS
            ? ['type' => $this->type, 'interval_days' => $this->intervalDays]
            : ['type' => $this->type, 'week_of_month' => $this->weekOfMonth, 'weekday' => $this->weekday];
    }

    public function label(): string
    {
        if ($this->type === self::EVERY_N_DAYS) {
            return $this->intervalDays === 1 ? 'Diario' : "Cada {$this->intervalDays} días";
        }

        return self::ORDINALS[$this->weekOfMonth].' '.self::WEEKDAY_NAMES[$this->weekday].' de cada mes';
    }

    /**
     * Fechas/hora que dicta la regla desde $start (incluida si cumple la regla) hasta $until
     * (inclusive, por día). Para `monthly_weekday`, $start solo aporta la hora y el mes inicial.
     *
     * @return array<int, Carbon>
     */
    public function occurrences(Carbon $start, Carbon $until): array
    {
        $limit = $until->copy()->endOfDay();
        $dates = [];

        if ($this->type === self::EVERY_N_DAYS) {
            for ($d = $start->copy(); $d->lte($limit) && count($dates) < self::MAX_OCCURRENCES; $d = $d->copy()->addDays($this->intervalDays)) {
                $dates[] = $d;
            }

            return $dates;
        }

        $month = $start->copy()->startOfMonth();
        while ($month->lte($limit) && count($dates) < self::MAX_OCCURRENCES) {
            $date = $this->nthWeekdayOf($month)->setTime($start->hour, $start->minute);
            if ($date->gte($start) && $date->lte($limit)) {
                $dates[] = $date;
            }
            $month = $month->copy()->addMonthNoOverflow();
        }

        return $dates;
    }

    private function nthWeekdayOf(Carbon $month): Carbon
    {
        if ($this->weekOfMonth === -1) {
            $d = $month->copy()->endOfMonth()->startOfDay();
            while ($d->dayOfWeek !== $this->weekday) {
                $d->subDay();
            }

            return $d;
        }

        $d = $month->copy()->startOfMonth();
        while ($d->dayOfWeek !== $this->weekday) {
            $d->addDay();
        }

        return $d->addWeeks($this->weekOfMonth - 1);
    }
}
