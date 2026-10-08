<?php

namespace Tests\Feature\Planning;

use App\Domain\Planning\Series\RecurrenceRule;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/** ZEUS-047: fechas que dicta cada tipo de regla de repetición. */
class RecurrenceRuleTest extends TestCase
{
    public function test_every_n_days_repeats_at_the_same_time_until_the_end_date(): void
    {
        $dates = RecurrenceRule::everyNDays(7)->occurrences(Carbon::parse('2026-10-05 12:00'), Carbon::parse('2026-11-02'));

        $this->assertSame(
            ['2026-10-05 12:00', '2026-10-12 12:00', '2026-10-19 12:00', '2026-10-26 12:00', '2026-11-02 12:00'],
            array_map(fn ($d) => $d->format('Y-m-d H:i'), $dates)
        );
    }

    public function test_monthly_first_monday_never_lands_on_another_weekday(): void
    {
        // Arranca el miércoles 07/10: el 1er lunes de octubre (05/10) ya pasó, así que no cuenta.
        $dates = RecurrenceRule::monthlyWeekday(1, Carbon::MONDAY)->occurrences(Carbon::parse('2026-10-07 12:00'), Carbon::parse('2027-01-31'));

        $this->assertSame(
            ['2026-11-02 12:00', '2026-12-07 12:00', '2027-01-04 12:00'],
            array_map(fn ($d) => $d->format('Y-m-d H:i'), $dates)
        );
    }

    public function test_monthly_last_friday(): void
    {
        $dates = RecurrenceRule::monthlyWeekday(-1, Carbon::FRIDAY)->occurrences(Carbon::parse('2026-10-01 10:30'), Carbon::parse('2026-12-31'));

        $this->assertSame(
            ['2026-10-30 10:30', '2026-11-27 10:30', '2026-12-25 10:30'],
            array_map(fn ($d) => $d->format('Y-m-d H:i'), $dates)
        );
    }

    public function test_occurrences_are_capped(): void
    {
        $dates = RecurrenceRule::everyNDays(1)->occurrences(Carbon::parse('2026-10-01 10:00'), Carbon::parse('2027-10-01'));

        $this->assertCount(RecurrenceRule::MAX_OCCURRENCES, $dates);
    }

    public function test_round_trips_through_array_and_labels_in_spanish(): void
    {
        $rule = RecurrenceRule::fromArray(RecurrenceRule::monthlyWeekday(1, Carbon::MONDAY)->toArray());

        $this->assertSame('1er lunes de cada mes', $rule->label());
        $this->assertSame('Cada 15 días', RecurrenceRule::everyNDays(15)->label());
    }

    public function test_rejects_invalid_rules(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecurrenceRule::fromArray(['type' => 'monthly_weekday', 'week_of_month' => 5, 'weekday' => 1]);
    }
}
