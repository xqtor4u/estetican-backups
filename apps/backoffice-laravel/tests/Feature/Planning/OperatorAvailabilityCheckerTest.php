<?php

namespace Tests\Feature\Planning;

use App\Domain\Planning\Services\OperatorAvailabilityChecker;
use App\Models\Client;
use App\Models\Operator;
use App\Models\OperatorUnavailability;
use App\Models\OperatorWeeklySchedule;
use App\Models\Pet;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OperatorAvailabilityCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function operator(string $name = 'Jose'): Operator
    {
        return Operator::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    private function bookingFor(int $operatorId, string $scheduledAt, int $durationMinutes = 60, string $status = 'scheduled'): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operatorId,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $durationMinutes,
            'status' => $status,
            'total_estimated_price' => 0,
        ]);
    }

    public function test_detects_exact_overlap(): void
    {
        $operator = $this->operator('Jose');
        $this->bookingFor($operator->id, '2026-07-10 10:00:00', 60);

        $checker = new OperatorAvailabilityChecker;

        $this->assertTrue($checker->hasConflict($operator->id, Carbon::parse('2026-07-10 10:30:00'), 30));
    }

    public function test_adjacent_slots_do_not_conflict(): void
    {
        $operator = $this->operator('Jose');
        $this->bookingFor($operator->id, '2026-07-10 10:00:00', 60);

        $checker = new OperatorAvailabilityChecker;

        $this->assertFalse($checker->hasConflict($operator->id, Carbon::parse('2026-07-10 11:00:00'), 30));
    }

    public function test_ignores_cancelled_bookings(): void
    {
        $operator = $this->operator('Jose');
        $this->bookingFor($operator->id, '2026-07-10 10:00:00', 60, 'cancelled');

        $checker = new OperatorAvailabilityChecker;

        $this->assertFalse($checker->hasConflict($operator->id, Carbon::parse('2026-07-10 10:30:00'), 30));
    }

    public function test_excludes_the_booking_being_edited(): void
    {
        $operator = $this->operator('Jose');
        $booking = $this->bookingFor($operator->id, '2026-07-10 10:00:00', 60);

        $checker = new OperatorAvailabilityChecker;

        $this->assertFalse($checker->hasConflict($operator->id, Carbon::parse('2026-07-10 10:00:00'), 60, $booking->id));
    }

    public function test_different_operator_has_no_conflict(): void
    {
        $operatorA = $this->operator('Jose');
        $operatorB = $this->operator('Maria');
        $this->bookingFor($operatorA->id, '2026-07-10 10:00:00', 60);

        $checker = new OperatorAvailabilityChecker;

        $this->assertFalse($checker->hasConflict($operatorB->id, Carbon::parse('2026-07-10 10:00:00'), 60));
    }

    public function test_operator_without_weekly_schedule_allows_any_time(): void
    {
        $operator = $this->operator('Jose');
        $checker = new OperatorAvailabilityChecker;

        // 2026-07-10 es viernes; sin ninguna fila configurada, no debe restringir nada.
        $this->assertFalse($checker->isOutsideWorkingHours($operator->id, Carbon::parse('2026-07-10 23:00:00'), 30));
    }

    public function test_detects_time_outside_configured_working_hours(): void
    {
        $operator = $this->operator('Jose');
        OperatorWeeklySchedule::create([
            'operator_id' => $operator->id,
            'day_of_week' => Carbon::parse('2026-07-10')->dayOfWeek, // viernes
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        $checker = new OperatorAvailabilityChecker;

        $this->assertTrue($checker->isOutsideWorkingHours($operator->id, Carbon::parse('2026-07-10 15:00:00'), 30));
    }

    public function test_allows_time_within_configured_working_hours(): void
    {
        $operator = $this->operator('Jose');
        OperatorWeeklySchedule::create([
            'operator_id' => $operator->id,
            'day_of_week' => Carbon::parse('2026-07-10')->dayOfWeek, // viernes
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        $checker = new OperatorAvailabilityChecker;

        $this->assertFalse($checker->isOutsideWorkingHours($operator->id, Carbon::parse('2026-07-10 10:00:00'), 30));
    }

    public function test_detects_day_off_when_other_days_are_configured(): void
    {
        $operator = $this->operator('Jose');
        // Solo lunes (1) configurado.
        OperatorWeeklySchedule::create([
            'operator_id' => $operator->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        $checker = new OperatorAvailabilityChecker;

        // 2026-07-10 es viernes, sin fila configurada para ese día.
        $this->assertTrue($checker->isOutsideWorkingHours($operator->id, Carbon::parse('2026-07-10 10:00:00'), 30));
    }

    public function test_detects_overlapping_time_off(): void
    {
        $operator = $this->operator('Jose');
        OperatorUnavailability::create([
            'operator_id' => $operator->id,
            'starts_at' => '2026-07-10 09:00:00',
            'ends_at' => '2026-07-10 18:00:00',
            'reason' => 'Vacaciones',
        ]);

        $checker = new OperatorAvailabilityChecker;

        $this->assertTrue($checker->hasTimeOff($operator->id, Carbon::parse('2026-07-10 10:00:00'), 30));
    }

    public function test_allows_time_adjacent_to_time_off(): void
    {
        $operator = $this->operator('Jose');
        OperatorUnavailability::create([
            'operator_id' => $operator->id,
            'starts_at' => '2026-07-10 09:00:00',
            'ends_at' => '2026-07-10 10:00:00',
        ]);

        $checker = new OperatorAvailabilityChecker;

        $this->assertFalse($checker->hasTimeOff($operator->id, Carbon::parse('2026-07-10 10:00:00'), 30));
    }
}
