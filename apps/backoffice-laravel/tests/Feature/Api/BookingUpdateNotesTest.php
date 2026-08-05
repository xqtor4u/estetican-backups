<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class BookingUpdateNotesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function authHeader(): array
    {
        return $this->createAdminAuthHeader();
    }

    private function booking(string $status = 'work_order', ?string $notes = 'Nota original'): SpaBooking
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
            'notes' => $notes,
        ]);
    }

    public function test_notes_can_be_edited_while_the_booking_is_in_process(): void
    {
        $booking = $this->booking('work_order', 'Nota original');

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'notes' => 'Nota actualizada durante el proceso',
        ]);

        $response->assertOk();
        $response->assertJsonPath('notes', 'Nota actualizada durante el proceso');
        $this->assertDatabaseHas('spa_bookings', ['id' => $booking->id, 'notes' => 'Nota actualizada durante el proceso']);
    }

    public function test_notes_can_be_cleared_to_null(): void
    {
        $booking = $this->booking('work_order', 'Nota original');

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'notes' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('notes', null);
        $this->assertDatabaseHas('spa_bookings', ['id' => $booking->id, 'notes' => null]);
    }

    public function test_updating_notes_does_not_touch_unrelated_fields(): void
    {
        $booking = $this->booking('work_order', 'Nota original');
        $originalOperatorId = $booking->operator_id;
        $originalScheduledAt = $booking->scheduled_at;

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'notes' => 'Solo la nota cambia',
        ]);

        $response->assertOk();
        $booking->refresh();
        $this->assertSame($originalOperatorId, $booking->operator_id);
        $this->assertTrue($originalScheduledAt->equalTo($booking->scheduled_at));
        $this->assertSame('work_order', $booking->status);
    }

    public function test_notes_can_be_edited_even_once_the_booking_is_completed(): void
    {
        $booking = $this->booking('completed', 'Nota original');

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'notes' => 'Corregida después de cerrar el servicio',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('spa_bookings', ['id' => $booking->id, 'notes' => 'Corregida después de cerrar el servicio']);
    }

    public function test_other_fields_stay_blocked_on_a_completed_booking_even_if_notes_is_also_sent(): void
    {
        $booking = $this->booking('completed', 'Nota original');

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'notes' => 'Intento de colar un cambio',
            'duration_minutes' => 999,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('spa_bookings', ['id' => $booking->id, 'notes' => 'Nota original']);
    }
}
