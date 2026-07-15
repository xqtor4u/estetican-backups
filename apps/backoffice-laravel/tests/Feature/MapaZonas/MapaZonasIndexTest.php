<?php

namespace Tests\Feature\MapaZonas;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapaZonasIndexTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-mapa-zonas-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    public function test_index_renders_and_contains_seeded_markers_of_all_four_types(): void
    {
        $branch = Branch::create(['code' => 'SUC01', 'name' => 'SucursalMapaTest', 'lat' => 19.111111, 'lng' => -99.111111, 'is_active' => true]);

        $client = Client::create(['first_name' => 'ClienteMapaTest', 'apellido_paterno' => 'Ruiz']);
        Address::create(['client_id' => $client->id, 'type' => 'home', 'street' => 'Calle 1', 'city' => 'CDMX', 'country' => 'México', 'lat' => 19.222222, 'lng' => -99.222222]);

        $pet = Pet::create(['client_id' => $client->id, 'name' => 'MascotaMapaTest', 'lat' => 19.333333, 'lng' => -99.333333]);

        $vehicle = Vehicle::create(['name' => 'VehiculoMapaTest', 'lat' => 19.444444, 'lng' => -99.444444, 'is_active' => true]);

        $response = $this->actingAs($this->admin())->get(route('mapa-zonas.index'));

        $response->assertOk();
        $response->assertSee($branch->name, false);
        $response->assertSee('ClienteMapaTest', false);
        $response->assertSee($pet->name, false);
        $response->assertSee($vehicle->name, false);
    }

    public function test_index_excludes_records_without_coordinates(): void
    {
        Branch::create(['code' => 'SUC02', 'name' => 'SucursalSinCoords', 'is_active' => true]);

        $client = Client::create(['first_name' => 'Cliente', 'apellido_paterno' => 'SinCoords']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'MascotaSinCoords']);

        $response = $this->actingAs($this->admin())->get(route('mapa-zonas.index'));

        $response->assertOk();

        $branchLabels = collect($response->viewData('branches'))->pluck('label');
        $petLabels = collect($response->viewData('pets'))->pluck('label');
        $unlocatedLabels = collect($response->viewData('unlocatedPets'))->pluck('label');

        $this->assertFalse($branchLabels->contains('SucursalSinCoords'));
        $this->assertFalse($petLabels->contains(fn ($label) => str_contains($label, 'MascotaSinCoords')));
        $this->assertTrue($unlocatedLabels->contains(fn ($label) => str_contains($label, 'MascotaSinCoords')));
    }
}
