<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingUnfulfillableStatusTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(): array
    {
        $user = User::create([
            'name' => 'Operador No Realizada',
            'first_name' => 'Operador',
            'apellido_paterno' => 'NoRealizada',
            'email' => 'operador-no-realizada-test-'.uniqid().'@example.com',
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

    private function booking(string $status): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => $status,
            'total_estimated_price' => 0,
        ]);
    }

    public function test_scheduled_booking_can_be_marked_unfulfillable_with_a_reason(): void
    {
        $booking = $this->booking('scheduled');

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'status' => 'unfulfillable',
            'cancellation_reason' => 'El animal no cooperó',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'unfulfillable');
        $this->assertDatabaseHas('spa_bookings', [
            'id' => $booking->id,
            'status' => 'unfulfillable',
            'cancellation_reason' => 'El animal no cooperó',
        ]);
    }

    public function test_work_order_booking_can_be_marked_unfulfillable_with_a_reason(): void
    {
        $booking = $this->booking('work_order');

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'status' => 'unfulfillable',
            'cancellation_reason' => 'El groomer se lastimó a mitad del servicio',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'unfulfillable');
        $this->assertDatabaseHas('spa_bookings', [
            'id' => $booking->id,
            'status' => 'unfulfillable',
            'cancellation_reason' => 'El groomer se lastimó a mitad del servicio',
        ]);
    }

    public function test_reason_is_optional(): void
    {
        $booking = $this->booking('work_order');

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'status' => 'unfulfillable',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'unfulfillable');
    }

    public function test_a_completed_booking_cannot_be_reopened_via_unfulfillable(): void
    {
        $booking = $this->booking('completed');

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'status' => 'unfulfillable',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('spa_bookings', ['id' => $booking->id, 'status' => 'completed']);
    }
}
