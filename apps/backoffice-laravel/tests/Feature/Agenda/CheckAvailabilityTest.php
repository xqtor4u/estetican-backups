<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\OperatorUnavailability;
use App\Models\OperatorWeeklySchedule;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class CheckAvailabilityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function operator(string $name = 'Jose'): Operator
    {
        return Operator::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    public function test_available_when_nothing_blocks(): void
    {
        $operator = $this->operator();

        $response = $this->actingAs($this->admin())->getJson('/agenda/check-availability?'.http_build_query([
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]));

        $response->assertOk();
        $response->assertJson(['available' => true, 'reason' => null]);
    }

    public function test_unavailable_outside_business_hours(): void
    {
        $operator = $this->operator();

        $response = $this->actingAs($this->admin())->getJson('/agenda/check-availability?'.http_build_query([
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(23, 0)->format('Y-m-d H:i:s'),
        ]));

        $response->assertOk();
        $response->assertJson(['available' => false]);
        $this->assertStringContainsString('horario operativo', $response->json('reason'));
    }

    public function test_unavailable_when_operator_has_conflicting_booking(): void
    {
        $operator = $this->operator();
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $response = $this->actingAs($this->admin())->getJson('/agenda/check-availability?'.http_build_query([
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        ]));

        $response->assertOk();
        $response->assertJson(['available' => false, 'reason' => 'El operador ya tiene una cita en ese horario.']);
    }

    public function test_unavailable_outside_operator_weekly_schedule(): void
    {
        $operator = $this->operator();
        $scheduledAt = now()->addDay()->setTime(11, 0);

        OperatorWeeklySchedule::create([
            'operator_id' => $operator->id,
            'day_of_week' => $scheduledAt->dayOfWeek,
            'start_time' => '15:00',
            'end_time' => '18:00',
        ]);

        $response = $this->actingAs($this->admin())->getJson('/agenda/check-availability?'.http_build_query([
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        ]));

        $response->assertOk();
        $response->assertJson(['available' => false, 'reason' => 'El operador no labora en el horario indicado.']);
    }

    public function test_unavailable_during_operator_time_off(): void
    {
        $operator = $this->operator();
        $scheduledAt = now()->addDay()->setTime(11, 0);

        OperatorUnavailability::create([
            'operator_id' => $operator->id,
            'starts_at' => $scheduledAt->copy()->startOfDay(),
            'ends_at' => $scheduledAt->copy()->endOfDay(),
            'reason' => 'Vacaciones',
        ]);

        $response = $this->actingAs($this->admin())->getJson('/agenda/check-availability?'.http_build_query([
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        ]));

        $response->assertOk();
        $response->assertJson(['available' => false, 'reason' => 'El operador no está disponible en ese periodo (vacaciones/permiso).']);
    }

    public function test_exclude_booking_id_ignores_its_own_booking_when_rescheduling(): void
    {
        $operator = $this->operator();
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $response = $this->actingAs($this->admin())->getJson('/agenda/check-availability?'.http_build_query([
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'exclude_booking_id' => $booking->id,
        ]));

        $response->assertOk();
        $response->assertJson(['available' => true, 'reason' => null]);
    }
}
