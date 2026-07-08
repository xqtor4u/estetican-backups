<?php

namespace Tests\Feature\MapaZonas;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-vehicle-crud-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    public function test_store_creates_a_vehicle(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('mapa-zonas.vehiculos.store'), [
                'name' => 'Camioneta 1',
                'lat' => 19.4,
                'lng' => -99.1,
                'notes' => 'Reparto zona norte',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('vehicles', ['name' => 'Camioneta 1']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('mapa-zonas.vehiculos.store'), [
                'lat' => 19.4,
                'lng' => -99.1,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_update_changes_name_and_coordinates(): void
    {
        $vehicle = Vehicle::create(['name' => 'Camioneta 2', 'lat' => 19.1, 'lng' => -99.1]);

        $response = $this->actingAs($this->admin())
            ->patchJson(route('mapa-zonas.vehiculos.update', $vehicle), [
                'name' => 'Camioneta 2 renombrada',
                'lat' => 19.5,
                'lng' => -99.5,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'name' => 'Camioneta 2 renombrada',
            'lat' => 19.5,
            'lng' => -99.5,
        ]);
    }

    public function test_destroy_removes_the_vehicle(): void
    {
        $vehicle = Vehicle::create(['name' => 'Camioneta 3', 'lat' => 19.1, 'lng' => -99.1]);

        $response = $this->actingAs($this->admin())
            ->deleteJson(route('mapa-zonas.vehiculos.destroy', $vehicle));

        $response->assertOk();
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }
}
