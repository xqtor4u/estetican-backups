<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Pet;
use App\Support\Geo\CoverageChecker;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageCheckerTest extends TestCase
{
    use RefreshDatabase;

    private const BRANCH_LAT = 21.8853;

    private const BRANCH_LNG = -102.2916;

    private function branch(): Branch
    {
        return Branch::create([
            'code' => 'MAIN',
            'name' => 'Sucursal Centro',
            'lat' => self::BRANCH_LAT,
            'lng' => self::BRANCH_LNG,
            'is_active' => true,
        ]);
    }

    private function petWithoutClientAddress(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    public function test_returns_null_when_no_coordinates_are_available(): void
    {
        $this->branch();
        $pet = $this->petWithoutClientAddress();

        $result = app(CoverageChecker::class)->checkPet($pet);

        $this->assertNull($result);
    }

    public function test_returns_null_when_pet_is_within_the_configured_radius(): void
    {
        $this->branch();
        $pet = $this->petWithoutClientAddress();
        $pet->update(['lat' => self::BRANCH_LAT + 0.01, 'lng' => self::BRANCH_LNG]); // ~1 km

        app(SystemSettings::class)->saveFields('coverage', ['coverage_radius_km' => 15]);

        $result = app(CoverageChecker::class)->checkPet($pet);

        $this->assertNull($result);
    }

    public function test_returns_warning_when_pet_coordinates_are_outside_the_radius(): void
    {
        $branch = $this->branch();
        $pet = $this->petWithoutClientAddress();
        // Ciudad de México — muy lejos de Aguascalientes
        $pet->update(['lat' => 19.4326, 'lng' => -99.1332]);

        app(SystemSettings::class)->saveFields('coverage', ['coverage_radius_km' => 15]);

        $result = app(CoverageChecker::class)->checkPet($pet);

        $this->assertNotNull($result);
        $this->assertSame(15, $result['radius_km']);
        $this->assertSame($branch->name, $result['branch_name']);
        $this->assertGreaterThan(15, $result['distance_km']);
    }

    public function test_falls_back_to_the_client_address_when_the_pet_has_no_coordinates(): void
    {
        $this->branch();
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        Address::create([
            'client_id' => $client->id,
            'type' => 'home',
            'street' => 'Reforma',
            'city' => 'Ciudad de México',
            'lat' => 19.4326,
            'lng' => -99.1332,
        ]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        app(SystemSettings::class)->saveFields('coverage', ['coverage_radius_km' => 15]);

        $result = app(CoverageChecker::class)->checkPet($pet);

        $this->assertNotNull($result);
        $this->assertGreaterThan(15, $result['distance_km']);
    }

    public function test_returns_null_when_no_active_branch_has_coordinates(): void
    {
        $pet = $this->petWithoutClientAddress();
        $pet->update(['lat' => 19.4326, 'lng' => -99.1332]);

        $result = app(CoverageChecker::class)->checkPet($pet);

        $this->assertNull($result);
    }
}
