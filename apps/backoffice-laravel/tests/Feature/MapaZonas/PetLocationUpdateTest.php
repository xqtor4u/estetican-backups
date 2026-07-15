<?php

namespace Tests\Feature\MapaZonas;

use App\Models\Client;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetLocationUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-pet-location-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    public function test_updating_pet_location_persists_lat_lng(): void
    {
        $pet = $this->pet();

        $response = $this->actingAs($this->admin())
            ->patchJson(route('mapa-zonas.pets.ubicacion', $pet), [
                'lat' => 19.432608,
                'lng' => -99.133209,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('pets', [
            'id' => $pet->id,
            'lat' => 19.432608,
            'lng' => -99.133209,
        ]);
    }

    public function test_updating_pet_location_validates_range(): void
    {
        $pet = $this->pet();

        $response = $this->actingAs($this->admin())
            ->patchJson(route('mapa-zonas.pets.ubicacion', $pet), [
                'lat' => 999,
                'lng' => -99.133209,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'lat' => null]);
    }
}
