<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\HotelReservation;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La vista Día de AgUniInd mostraba las mismas citas SPA dos veces (tarjetas de
 * "Bloques horarios visibles" + tabla de abajo) — la sección de tarjetas ahora
 * solo lista Hotel (que la tabla, SpaBooking-only, no cubre); SPA vive solo en la tabla.
 */
class AgendaDayViewDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-agenda-dedup-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    public function test_a_spa_booking_appears_in_the_table_but_not_in_the_timeline_cards(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => true]);

        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'MascotaUnicaSpa']);
        $service = Service::create(['code' => 'ST'.uniqid(), 'name' => 'Servicio', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => 'scheduled',
            'total_estimated_price' => 100,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 100]);

        $response = $this->actingAs($this->admin())->get(route('agenda.index', ['status_touched' => 1]));

        $response->assertOk();
        $html = $response->getContent();

        // Aparece una sola vez en toda la página (solo la tabla) — no dos.
        $this->assertSame(1, substr_count($html, 'MascotaUnicaSpa'));
    }

    public function test_a_hotel_reservation_still_appears_in_the_timeline_cards(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => true]);

        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'MascotaUnicaHotel']);
        HotelReservation::create([
            'pet_id' => $pet->id,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->admin())->get(route('agenda.index', ['status_touched' => 1]));

        $response->assertOk();
        $response->assertSee('Estancias de Hotel');
        $response->assertSee('MascotaUnicaHotel');
    }

    public function test_the_hotel_timeline_section_is_hidden_when_the_module_is_disabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => false]);

        $response = $this->actingAs($this->admin())->get(route('agenda.index', ['status_touched' => 1]));

        $response->assertOk();
        $response->assertDontSee('Estancias de Hotel');
    }

    /**
     * Bug real encontrado y corregido de paso: la sección de Hotel y la tabla SPA
     * compartían el mismo `@if` — al agregarle la condición del módulo de Hotel a
     * ese `@if`, la tabla entera (SPA) habría desaparecido con el módulo apagado,
     * ya que dependía de la MISMA condición sin necesidad. Nunca se vio en vivo
     * porque el módulo estuvo encendido, pero es un regresión real evitable.
     */
    public function test_the_spa_table_still_renders_when_the_hotel_module_is_disabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => false]);

        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'MascotaConHotelApagado']);
        $service = Service::create(['code' => 'ST'.uniqid(), 'name' => 'Servicio', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => 'scheduled',
            'total_estimated_price' => 100,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 100]);

        $response = $this->actingAs($this->admin())->get(route('agenda.index', ['status_touched' => 1]));

        $response->assertOk();
        $response->assertSee('MascotaConHotelApagado');
    }
}
