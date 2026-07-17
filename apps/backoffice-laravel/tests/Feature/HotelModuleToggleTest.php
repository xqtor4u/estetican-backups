<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\HotelReservation;
use App\Models\Pet;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Hotel Module Test',
            'first_name' => 'Hotel',
            'apellido_paterno' => 'Test',
            'email' => 'hotel-module-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function activeReservation(): HotelReservation
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return HotelReservation::create([
            'pet_id' => $pet->id,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);
    }

    public function test_hotel_reservation_routes_are_blocked_when_the_module_is_disabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => false]);

        $response = $this->actingAs($this->admin())->get(route('hotel-reservations.index'));
        $response->assertNotFound();

        $response = $this->actingAs($this->admin())->get(route('hotel-reservations.create'));
        $response->assertNotFound();
    }

    public function test_hotel_reservation_routes_are_reachable_when_the_module_is_enabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => true]);
        $user = $this->admin();

        $this->actingAs($user)->get(route('hotel-reservations.index'))->assertOk();
        $this->actingAs($user)->get(route('hotel-reservations.create'))->assertOk();
    }

    public function test_active_reservation_does_not_appear_in_agenda_timeline_when_disabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => false]);
        $reservation = $this->activeReservation();

        $response = $this->actingAs($this->admin())->get(route('agenda.index'));

        $response->assertOk();
        $response->assertDontSee($reservation->pet->name);
    }

    public function test_active_reservation_appears_in_agenda_timeline_when_enabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => true]);
        $reservation = $this->activeReservation();

        $response = $this->actingAs($this->admin())->get(route('agenda.index'));

        $response->assertOk();
        $response->assertSee($reservation->pet->name);
        $response->assertSee('Estancia Hotel');
    }

    public function test_dashboard_hides_hotel_kpi_when_disabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => false]);

        $response = $this->actingAs($this->admin())->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertDontSee('Huéspedes en Hotel');
        $response->assertDontSee('Nueva estancia Hotel');
    }

    public function test_dashboard_shows_hotel_kpi_when_enabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => true]);

        $response = $this->actingAs($this->admin())->get(route('dashboard.index'));

        $response->assertOk();
        $response->assertSee('Huéspedes en Hotel');
        $response->assertSee('Nueva estancia Hotel');
    }

    public function test_global_create_hides_hotel_option_when_disabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => false]);

        $response = $this->actingAs($this->admin())->get(route('agenda.create'));

        $response->assertOk();
        $response->assertDontSee('Hospedaje (Hotel)');
        $response->assertSee('Servicio de SPA');
    }

    public function test_global_create_shows_hotel_option_when_enabled(): void
    {
        app(SystemSettings::class)->saveFields('hotel', ['hotel_module_enabled' => true]);

        $response = $this->actingAs($this->admin())->get(route('agenda.create'));

        $response->assertOk();
        $response->assertSee('Hospedaje (Hotel)');
    }
}
