<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaBookingCoverageWarningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-coverage-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
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

    private function service(): Service
    {
        return Service::create(['code' => 'BC01', 'name' => 'Baño y corte', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60]);
    }

    public function test_flashes_a_warning_when_the_pet_is_outside_the_coverage_radius(): void
    {
        $this->branch();
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka', 'lat' => 19.4326, 'lng' => -99.1332]);
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'full_name' => 'Jose', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertRedirect(route('agenda.index'));
        $response->assertSessionHas('warning');
        $response->assertSessionHas('success');
    }

    public function test_does_not_flash_a_warning_when_the_pet_is_within_coverage(): void
    {
        $this->branch();
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka', 'lat' => 21.89, 'lng' => -102.29]);
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'full_name' => 'Jose', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertRedirect(route('agenda.index'));
        $response->assertSessionMissing('warning');
    }
}
