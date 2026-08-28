<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Operator;
use App\Models\OperatorUnavailability;
use App\Models\OperatorWeeklySchedule;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class SpaBookingSchedulingValidationTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
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

    public function test_rejects_booking_when_no_service_has_an_operator(): void
    {
        $pet = $this->pet();
        $service = $this->service();

        // Sin operador responsable y sin operador por servicio.
        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_the_responsible_operator_falls_back_to_the_first_service_operator(): void
    {
        $pet = $this->pet();
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        // Sin `operator_id`, pero el servicio sí trae operador.
        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$service->id],
            'service_operators' => [$service->id => $operator->id],
        ]);

        $response->assertRedirect(route('agenda.index'));
        $this->assertDatabaseHas('spa_bookings', ['pet_id' => $pet->id, 'operator_id' => $operator->id]);
        $this->assertDatabaseHas('spa_booking_services', ['service_id' => $service->id, 'operator_id' => $operator->id]);
    }

    public function test_rejects_booking_outside_business_hours(): void
    {
        $pet = $this->pet();
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

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
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
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

    public function test_rejects_booking_outside_operator_weekly_schedule(): void
    {
        $pet = $this->pet();
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        OperatorWeeklySchedule::create([
            'operator_id' => $operator->id,
            'day_of_week' => $scheduledAt->dayOfWeek,
            'start_time' => '15:00',
            'end_time' => '18:00',
        ]);

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_rejects_booking_during_operator_time_off(): void
    {
        $pet = $this->pet();
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        OperatorUnavailability::create([
            'operator_id' => $operator->id,
            'starts_at' => $scheduledAt->copy()->startOfDay(),
            'ends_at' => $scheduledAt->copy()->endOfDay(),
            'reason' => 'Vacaciones',
        ]);

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'services' => [$service->id],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_accepts_a_valid_booking(): void
    {
        $pet = $this->pet();
        $service = $this->service();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

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
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
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

    private function op(string $name): Operator
    {
        return Operator::create(['code' => 'OP'.uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    public function test_per_service_operator_overrides_the_global_operator_on_that_line(): void
    {
        $pet = $this->pet();
        $bath = Service::create(['code' => 'B01', 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);
        $cut = Service::create(['code' => 'C01', 'name' => 'Corte', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);
        $general = $this->op('General');
        $specialist = $this->op('Especialista');

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $general->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$bath->id, $cut->id],
            'service_operators' => [$cut->id => $specialist->id],
        ]);

        $response->assertRedirect(route('agenda.index'));
        $booking = SpaBooking::firstOrFail();
        $this->assertSame($general->id, $booking->operator_id); // responsable de la cita
        $this->assertDatabaseHas('spa_booking_services', [
            'spa_booking_id' => $booking->id, 'service_id' => $bath->id, 'operator_id' => $general->id,
        ]);
        $this->assertDatabaseHas('spa_booking_services', [
            'spa_booking_id' => $booking->id, 'service_id' => $cut->id, 'operator_id' => $specialist->id,
        ]);
    }

    public function test_per_service_durations_set_the_booking_total_duration(): void
    {
        $pet = $this->pet();
        $bath = Service::create(['code' => 'B01', 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);
        $cut = Service::create(['code' => 'C01', 'name' => 'Corte', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);
        $operator = $this->op('Jose');

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$bath->id, $cut->id],
            'service_durations' => [$bath->id => 45, $cut->id => 20],
        ]);

        $response->assertRedirect(route('agenda.index'));
        $this->assertSame(65, (int) SpaBooking::firstOrFail()->duration_minutes);
    }

    public function test_a_per_service_operator_with_a_conflict_in_its_own_segment_is_rejected(): void
    {
        $pet = $this->pet();
        $bath = Service::create(['code' => 'B01', 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);
        $cut = Service::create(['code' => 'C01', 'name' => 'Corte', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);
        $general = $this->op('General');
        $busy = $this->op('Ocupado');
        $start = now()->addDay()->setTime(11, 0);

        // 'Ocupado' ya tiene una cita en el 2º tramo (11:30–12:00).
        SpaBooking::create([
            'pet_id' => $pet->id, 'operator_id' => $busy->id,
            'scheduled_at' => $start->copy()->addMinutes(30), 'duration_minutes' => 30,
            'status' => 'scheduled', 'total_estimated_price' => 0,
        ]);

        $response = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'operator_id' => $general->id,
            'scheduled_at' => $start->format('Y-m-d H:i:s'),
            'services' => [$bath->id, $cut->id],
            'service_operators' => [$cut->id => $busy->id],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('spa_bookings', 1); // solo la cita previa de 'Ocupado'
    }
}
