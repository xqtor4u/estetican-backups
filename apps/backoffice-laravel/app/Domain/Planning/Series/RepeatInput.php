<?php

namespace App\Domain\Planning\Series;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * ZEUS-047: campos "Repetir esta cita" del alta de cita — mismos nombres en el formulario web
 * (`agenda/create`) y en el alta móvil (`POST /api/bookings`).
 *
 * - repeat_enabled: bool
 * - repeat_mode: 7 | 15 | 30 | custom (repeat_interval_days) | monthly (repeat_week_of_month;
 *   sin él, se deduce de la fecha: "el 1er lunes" si la primera cita cae en el primer lunes)
 * - repeat_until: 6m | 1y | date (repeat_until_date)
 */
final class RepeatInput
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'repeat_enabled' => 'nullable|boolean',
            'repeat_mode' => ['required_if:repeat_enabled,1,true', 'nullable', Rule::in(['7', '15', '30', 'custom', 'monthly'])],
            'repeat_interval_days' => 'required_if:repeat_mode,custom|nullable|integer|min:1|max:365',
            'repeat_week_of_month' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, -1])],
            'repeat_until' => ['required_if:repeat_enabled,1,true', 'nullable', Rule::in(['6m', '1y', 'date'])],
            'repeat_until_date' => 'required_if:repeat_until,date|nullable|date',
        ];
    }

    /** @param array<string, mixed> $input */
    public static function enabled(array $input): bool
    {
        return filter_var($input['repeat_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /** @param array<string, mixed> $input */
    public static function rule(array $input, Carbon $start): RecurrenceRule
    {
        return match ((string) ($input['repeat_mode'] ?? '')) {
            'custom' => RecurrenceRule::everyNDays((int) $input['repeat_interval_days']),
            'monthly' => RecurrenceRule::monthlyWeekday(
                (int) ($input['repeat_week_of_month'] ?? self::weekOfMonth($start)),
                $start->dayOfWeek
            ),
            default => RecurrenceRule::everyNDays((int) $input['repeat_mode']),
        };
    }

    /** @param array<string, mixed> $input */
    public static function endsOn(array $input, Carbon $start): Carbon
    {
        return match ((string) ($input['repeat_until'] ?? '')) {
            '6m' => $start->copy()->addMonthsNoOverflow(6),
            'date' => Carbon::parse($input['repeat_until_date'])->endOfDay(),
            default => $start->copy()->addYear(),
        };
    }

    /** 1–4 según qué número de ese día de la semana es en su mes; el 5º cuenta como "último". */
    public static function weekOfMonth(Carbon $date): int
    {
        $nth = intdiv($date->day - 1, 7) + 1;

        return $nth >= 5 ? -1 : $nth;
    }
}
