<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaBookingSchedulingValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-scheduling-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function service(): Service
    {
        return Service::create(['code' => 'BC01', 'name' => 'Baño y corte', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60]);
    }

    public function test_rejects_booking_without_operator(): void
    {
        $pet = $this->pet();
        $service = $this->service();

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertSessionHasErrors('operator_id');
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_rejects_booking_outside_business_hours(): void
    {
        $pet = $this->pet();
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'full_name' => 'Jose', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(21, 0)->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_rejects_overlapping_operator_booking(): void
    {
        $pet = $this->pet();
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'full_name' => 'Jose', 'is_active' => true]);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('spa_bookings', 1);
    }

    public function test_accepts_a_valid_booking(): void
    {
        $pet = $this->pet();
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'full_name' => 'Jose', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertRedirect(route('agenda.index'));
        $this->assertDatabaseHas('spa_bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
        ]);
    }

    public function test_records_the_authenticated_user_as_creator(): void
    {
        $pet = $this->pet();
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'full_name' => 'Jose', 'is_active' => true]);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('pets.bookings.store', $pet), [
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertRedirect(route('agenda.index'));
        $this->assertDatabaseHas('spa_bookings', [
            'pet_id' => $pet->id,
            'created_by_user_id' => $admin->id,
        ]);
    }
}
