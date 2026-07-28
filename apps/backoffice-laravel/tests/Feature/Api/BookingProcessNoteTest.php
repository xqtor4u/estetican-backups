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

class BookingProcessNoteTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaderAndUser(): array
    {
        $user = User::create([
            'name' => 'Operador Notas',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Notas',
            'email' => 'operador-notas-test-'.uniqid().'@example.com',
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

    private function booking(string $status = 'work_order'): SpaBooking
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

    public function test_store_creates_a_process_note_with_the_authenticated_user_as_author(): void
    {
        ['headers' => $headers, 'user' => $user] = $this->authHeaderAndUser();
        $booking = $this->booking();

        $response = $this->withHeaders($headers)->postJson("/api/bookings/{$booking->id}/process-notes", [
            'note' => 'Se detectó nudo en la pata trasera.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('note', 'Se detectó nudo en la pata trasera.');
        $response->assertJsonPath('author', $user->name);
        $this->assertDatabaseHas('booking_process_notes', [
            'spa_booking_id' => $booking->id,
            'user_id' => $user->id,
            'note' => 'Se detectó nudo en la pata trasera.',
        ]);
    }

    public function test_index_lists_notes_for_the_booking_in_chronological_order(): void
    {
        ['headers' => $headers] = $this->authHeaderAndUser();
        $booking = $this->booking();

        $this->withHeaders($headers)->postJson("/api/bookings/{$booking->id}/process-notes", ['note' => 'Primera nota']);
        $this->withHeaders($headers)->postJson("/api/bookings/{$booking->id}/process-notes", ['note' => 'Segunda nota']);

        $response = $this->withHeaders($headers)->getJson("/api/bookings/{$booking->id}/process-notes");

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonPath('0.note', 'Primera nota');
        $response->assertJsonPath('1.note', 'Segunda nota');
    }

    public function test_update_edits_an_existing_note(): void
    {
        ['headers' => $headers] = $this->authHeaderAndUser();
        $booking = $this->booking();

        $created = $this->withHeaders($headers)->postJson("/api/bookings/{$booking->id}/process-notes", ['note' => 'Borrador'])->json();

        $response = $this->withHeaders($headers)->patchJson("/api/bookings/{$booking->id}/process-notes/{$created['id']}", [
            'note' => 'Nota completada al cobrar',
        ]);

        $response->assertOk();
        $response->assertJsonPath('note', 'Nota completada al cobrar');
        $this->assertDatabaseHas('booking_process_notes', [
            'id' => $created['id'],
            'note' => 'Nota completada al cobrar',
        ]);
    }

    public function test_update_rejects_a_note_that_belongs_to_a_different_booking(): void
    {
        ['headers' => $headers] = $this->authHeaderAndUser();
        $bookingA = $this->booking();
        $bookingB = $this->booking();

        $created = $this->withHeaders($headers)->postJson("/api/bookings/{$bookingA->id}/process-notes", ['note' => 'Nota A'])->json();

        $response = $this->withHeaders($headers)->patchJson("/api/bookings/{$bookingB->id}/process-notes/{$created['id']}", [
            'note' => 'Intento cruzado',
        ]);

        $response->assertStatus(404);
    }

    public function test_store_is_blocked_once_the_booking_is_completed(): void
    {
        ['headers' => $headers] = $this->authHeaderAndUser();
        $booking = $this->booking('completed');

        $response = $this->withHeaders($headers)->postJson("/api/bookings/{$booking->id}/process-notes", [
            'note' => 'Demasiado tarde',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_is_allowed_even_once_the_booking_is_completed(): void
    {
        ['headers' => $headers] = $this->authHeaderAndUser();
        $booking = $this->booking('work_order');

        $created = $this->withHeaders($headers)->postJson("/api/bookings/{$booking->id}/process-notes", ['note' => 'Borrador'])->json();
        $booking->update(['status' => 'completed']);

        $response = $this->withHeaders($headers)->patchJson("/api/bookings/{$booking->id}/process-notes/{$created['id']}", [
            'note' => 'Corregida después de cerrar el servicio',
        ]);

        $response->assertOk();
        $response->assertJsonPath('note', 'Corregida después de cerrar el servicio');
    }
}
