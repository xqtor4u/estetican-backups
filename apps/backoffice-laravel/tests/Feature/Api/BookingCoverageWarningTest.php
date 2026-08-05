<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class BookingCoverageWarningTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function authHeader(): array
    {
        return $this->createAdminAuthHeader();
    }

    private function branch(): void
    {
        Branch::create([
            'code' => 'MAIN',
            'name' => 'Sucursal Centro',
            'lat' => 21.8853,
            'lng' => -102.2916,
            'is_active' => true,
        ]);

        app(SystemSettings::class)->saveFields('coverage', ['coverage_radius_km' => 15]);
    }

    public function test_response_includes_coverage_warning_when_pet_is_outside_the_radius(): void
    {
        $this->branch();
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka', 'lat' => 19.4326, 'lng' => -99.1332]);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('coverage_warning', fn ($value) => is_string($value) && str_contains($value, 'fuera del radio de cobertura'));
    }

    public function test_response_coverage_warning_is_null_when_pet_is_within_the_radius(): void
    {
        $this->branch();
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka', 'lat' => 21.89, 'lng' => -102.29]);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('coverage_warning', null);
    }
}
