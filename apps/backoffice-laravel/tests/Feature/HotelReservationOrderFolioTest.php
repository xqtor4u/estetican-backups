<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentSeries;
use App\Models\HotelReservation;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hotel ya tenía la misma infraestructura de folio que SPA (order_series_id/order_folio
 * en el modelo, serie "orden_hotel" con prefijo OT-HOT- ya sembrada) pero nunca se
 * conectó en ningún controlador — nunca se le asignaba folio a una reserva real.
 */
class HotelReservationOrderFolioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Hotel',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Hotel',
            'email' => 'admin-hotel-folio-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    public function test_creating_a_hotel_reservation_assigns_an_order_folio(): void
    {
        DocumentSeries::create([
            'document_type' => 'orden_hotel',
            'name' => 'Órdenes de estancia Hotel',
            'prefix' => 'OT-HOT-',
            'next_number' => 1,
            'padding' => 6,
            'is_active' => true,
        ]);

        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Nina']);

        $this->actingAs($this->admin())->post(route('hotel-reservations.store'), [
            'pet_id' => $pet->id,
            'start_at' => '2026-03-28 09:00:00',
            'end_at' => '2026-03-29 18:00:00',
        ])->assertRedirect();

        $reservation = HotelReservation::firstOrFail();

        $this->assertSame('OT-HOT-000001', $reservation->order_folio);
    }

    public function test_creating_a_hotel_reservation_without_an_active_series_does_not_break_the_flow(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Nina']);

        $response = $this->actingAs($this->admin())->post(route('hotel-reservations.store'), [
            'pet_id' => $pet->id,
            'start_at' => '2026-03-28 09:00:00',
            'end_at' => '2026-03-29 18:00:00',
        ]);

        $response->assertRedirect();
        $this->assertNull(HotelReservation::firstOrFail()->order_folio);
    }
}
