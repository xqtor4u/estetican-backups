<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Operator;
use App\Models\OperatorUnavailability;
use App\Models\OperatorWeeklySchedule;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingSchedulingValidationTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(): array
    {
        $user = User::create([
            'name' => 'Operador Test',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test',
            'email' => 'operador-api-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);

        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['Authorization' => "Bearer {$plainToken}"];
    }

    /** @return array{headers: array, user: User} */
    private function authHeaderAndUser(): array
    {
        $user = User::create([
            'name' => 'Operador Test 2',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test 2',
            'email' => 'operador-api-test-2-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);

        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['headers' => ['Authorization' => "Bearer {$plainToken}"], 'user' => $user];
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    public function test_rejects_booking_without_operator(): void
    {
        $pet = $this->pet();

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('operator_id');
    }

    public function test_rejects_booking_outside_business_hours(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(21, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_rejects_overlapping_operator_booking(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('spa_bookings', 1);
    }

    public function test_accepts_a_valid_booking(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('spa_bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
        ]);
    }

    public function test_rejects_booking_outside_operator_weekly_schedule(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        OperatorWeeklySchedule::create([
            'operator_id' => $operator->id,
            'day_of_week' => $scheduledAt->dayOfWeek,
            'start_time' => '15:00',
            'end_time' => '18:00',
        ]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_rejects_booking_during_operator_time_off(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        OperatorUnavailability::create([
            'operator_id' => $operator->id,
            'starts_at' => $scheduledAt->copy()->startOfDay(),
            'ends_at' => $scheduledAt->copy()->endOfDay(),
            'reason' => 'Vacaciones',
        ]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_records_the_authenticated_user_as_creator(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        ['headers' => $headers, 'user' => $user] = $this->authHeaderAndUser();

        $response = $this->withHeaders($headers)->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('spa_bookings', [
            'pet_id' => $pet->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_cannot_reschedule_a_booking_that_already_started(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => 0,
        ]);
        $originalScheduledAt = $booking->scheduled_at;

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $booking->refresh();
        $this->assertTrue($originalScheduledAt->equalTo($booking->scheduled_at));
        $this->assertSame('work_order', $booking->status);
    }

    public function test_can_still_edit_other_fields_of_a_started_booking_without_touching_the_schedule(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'notes' => 'El animal llegó agitado, se calmó a los 10 min',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('spa_bookings', [
            'id' => $booking->id,
            'notes' => 'El animal llegó agitado, se calmó a los 10 min',
        ]);
    }
}
