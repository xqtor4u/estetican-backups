<?php

namespace Tests\Feature\Planning;

use App\Domain\Planning\Services\OperatorAvailabilityChecker;
use App\Models\Client;
use App\Models\Operator;
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
}
