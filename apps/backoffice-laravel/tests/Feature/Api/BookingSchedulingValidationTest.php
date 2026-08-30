<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Operator;
use App\Models\OperatorRole;
use App\Models\OperatorUnavailability;
use App\Models\OperatorWeeklySchedule;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class BookingSchedulingValidationTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function authHeader(): array
    {
        return $this->createAdminAuthHeader();
    }

    /** @return array{headers: array, user: User} */
    private function authHeaderAndUser(): array
    {
        $user = $this->createAdminUser();

        return ['headers' => $this->createAdminAuthHeader($user), 'user' => $user];
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    public function test_rejects_booking_without_operator(): void
    {
        $pet = $this->pet();

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('operator_id');
    }

    public function test_rejects_booking_outside_business_hours(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(21, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_rejects_overlapping_operator_booking(): void
    {
        $pet = $this->pet();
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

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('spa_bookings', 1);
    }

    public function test_accepts_a_valid_booking(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('spa_bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
        ]);
    }

    public function test_rejects_booking_outside_operator_weekly_schedule(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        OperatorWeeklySchedule::create([
            'operator_id' => $operator->id,
            'day_of_week' => $scheduledAt->dayOfWeek,
            'start_time' => '15:00',
            'end_time' => '18:00',
        ]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_rejects_booking_during_operator_time_off(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        OperatorUnavailability::create([
            'operator_id' => $operator->id,
            'starts_at' => $scheduledAt->copy()->startOfDay(),
            'ends_at' => $scheduledAt->copy()->endOfDay(),
            'reason' => 'Vacaciones',
        ]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_records_the_authenticated_user_as_creator(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        ['headers' => $headers, 'user' => $user] = $this->authHeaderAndUser();

        $response = $this->withHeaders($headers)->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('spa_bookings', [
            'pet_id' => $pet->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_cannot_reschedule_a_booking_that_already_started(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => 0,
        ]);
        $originalScheduledAt = $booking->scheduled_at;

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $booking->refresh();
        $this->assertTrue($originalScheduledAt->equalTo($booking->scheduled_at));
        $this->assertSame('work_order', $booking->status);
    }

    public function test_can_still_edit_other_fields_of_a_started_booking_without_touching_the_schedule(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'notes' => 'El animal llegó agitado, se calmó a los 10 min',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('spa_bookings', [
            'id' => $booking->id,
            'notes' => 'El animal llegó agitado, se calmó a los 10 min',
        ]);
    }

    private function service(string $name, int $durationMinutes, float $price = 100): Service
    {
        return Service::create([
            'code' => 'SVC'.uniqid(),
            'type' => 'spa',
            'name' => $name,
            'price' => $price,
            'duration_minutes' => $durationMinutes,
        ]);
    }

    public function test_accepts_a_booking_with_a_different_operator_per_service(): void
    {
        $pet = $this->pet();
        $primary = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Juan', 'first_name' => 'Juan', 'is_active' => true]);
        $second = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Maria', 'first_name' => 'Maria', 'is_active' => true]);
        $bath = $this->service('Baño', 30);
        $cut = $this->service('Corte', 20);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $primary->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 50,
            'services' => [
                ['id' => $bath->id],
                ['id' => $cut->id, 'operator_id' => $second->id],
            ],
        ]);

        $response->assertStatus(201);
        $booking = SpaBooking::firstOrFail();
        $this->assertDatabaseHas('spa_booking_services', [
            'spa_booking_id' => $booking->id,
            'service_id' => $bath->id,
            'operator_id' => $primary->id,
        ]);
        $this->assertDatabaseHas('spa_booking_services', [
            'spa_booking_id' => $booking->id,
            'service_id' => $cut->id,
            'operator_id' => $second->id,
        ]);
    }

    public function test_service_lines_without_an_explicit_operator_default_to_the_booking_operator(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $bath = $this->service('Baño', 30);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [['id' => $bath->id]],
        ]);

        $response->assertStatus(201);
        $booking = SpaBooking::firstOrFail();
        $this->assertDatabaseHas('spa_booking_services', [
            'spa_booking_id' => $booking->id,
            'service_id' => $bath->id,
            'operator_id' => $operator->id,
        ]);
    }

    public function test_rejects_when_the_second_service_operator_has_a_conflict_in_its_own_segment(): void
    {
        $pet = $this->pet();
        $primary = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Juan', 'first_name' => 'Juan', 'is_active' => true]);
        $second = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Maria', 'first_name' => 'Maria', 'is_active' => true]);
        $bath = $this->service('Baño', 30);
        $cut = $this->service('Corte', 20);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        // Maria ya tiene una cita justo en lo que sería su propio segmento (11:30-11:50, cuando
        // el baño ya terminó y le tocaría empezar el corte).
        SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $second->id,
            'scheduled_at' => $scheduledAt->copy()->addMinutes(30),
            'duration_minutes' => 20,
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $primary->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 50,
            'services' => [
                ['id' => $bath->id],
                ['id' => $cut->id, 'operator_id' => $second->id],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'El operador asignado a "Corte" ya tiene una cita en ese horario.']);
        $this->assertDatabaseCount('spa_bookings', 1);
    }

    public function test_accepts_when_the_second_operator_is_only_busy_during_the_first_service_segment(): void
    {
        $pet = $this->pet();
        $primary = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Juan', 'first_name' => 'Juan', 'is_active' => true]);
        $second = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Maria', 'first_name' => 'Maria', 'is_active' => true]);
        $bath = $this->service('Baño', 30);
        $cut = $this->service('Corte', 20);
        $scheduledAt = now()->addDay()->setTime(11, 0);

        // Maria tiene una cita de 11:00 a 11:20 — termina antes de que empiece su propio
        // segmento (11:30, cuando el baño de esta cita nueva ya terminó). No debería bloquear.
        SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $second->id,
            'scheduled_at' => $scheduledAt->copy(),
            'duration_minutes' => 20,
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $primary->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 50,
            'services' => [
                ['id' => $bath->id],
                ['id' => $cut->id, 'operator_id' => $second->id],
            ],
        ]);

        $response->assertStatus(201);
    }

    public function test_transitioning_to_work_order_via_update_starts_every_pending_line(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $bath = $this->service('Baño', 30);
        $cut = $this->service('Corte', 20);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);
        $bathLine = $booking->services()->create(['service_id' => $bath->id, 'current_price' => 100]);
        $cutLine = $booking->services()->create(['service_id' => $cut->id, 'current_price' => 100]);

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'status' => 'work_order',
        ]);

        $response->assertOk();
        $bathLine->refresh();
        $cutLine->refresh();
        $this->assertNotNull($bathLine->started_at, 'el inicio instantáneo/"Realizada" debe arrancar todas las líneas de un jalón');
        $this->assertNotNull($cutLine->started_at);
    }

    /* ── Calificación operador↔servicio (SYNC-043) ─────────────────────────── */

    private function serviceRequiringRole(OperatorRole $role, string $name = 'Consulta', int $durationMinutes = 30): Service
    {
        return Service::create([
            'code' => 'SVC'.uniqid(),
            'type' => 'spa',
            'name' => $name,
            'price' => 100,
            'duration_minutes' => $durationMinutes,
            'operator_role_id' => $role->id,
        ]);
    }

    public function test_rejects_a_service_line_whose_operator_lacks_the_required_role(): void
    {
        $pet = $this->pet();
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        $vet = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra', 'first_name' => 'Dra', 'is_active' => true]);
        $vet->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);
        $groomer = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Estil', 'first_name' => 'Estil', 'is_active' => true]);
        $consulta = $this->serviceRequiringRole($role);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $groomer->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [['id' => $consulta->id, 'operator_id' => $groomer->id]],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_accepts_a_service_line_when_the_operator_has_the_required_role(): void
    {
        $pet = $this->pet();
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        $vet = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra', 'first_name' => 'Dra', 'is_active' => true]);
        $vet->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);
        $consulta = $this->serviceRequiringRole($role);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $vet->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [['id' => $consulta->id, 'operator_id' => $vet->id]],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('spa_booking_services', [
            'service_id' => $consulta->id,
            'operator_id' => $vet->id,
        ]);
    }

    public function test_a_per_line_custom_duration_is_used_to_segment_the_availability_check(): void
    {
        $pet = $this->pet();
        $a = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'A', 'first_name' => 'A', 'is_active' => true]);
        $b = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'B', 'first_name' => 'B', 'is_active' => true]);
        $bath = $this->service('Baño', 30);
        $cut = $this->service('Corte', 20);
        $start = now()->addDay()->setTime(11, 0);

        // B ocupado 11:30–11:50: chocaría con su segmento si el baño durara los 30 min de
        // catálogo, pero el usuario lo alarga a 60 → a B le toca recién a las 12:00, sin choque.
        SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $b->id,
            'scheduled_at' => $start->copy()->addMinutes(30),
            'duration_minutes' => 20,
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $a->id,
            'scheduled_at' => $start->format('Y-m-d H:i:s'),
            'duration_minutes' => 80,
            'services' => [
                ['id' => $bath->id, 'operator_id' => $a->id, 'duration_minutes' => 60],
                ['id' => $cut->id, 'operator_id' => $b->id, 'duration_minutes' => 20],
            ],
        ]);

        $response->assertStatus(201);
    }

    public function test_a_service_without_a_required_role_accepts_any_operator(): void
    {
        $pet = $this->pet();
        $anyone = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Quien', 'first_name' => 'Quien', 'is_active' => true]);
        $bath = $this->service('Baño', 30); // operator_role_id queda null

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $anyone->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [['id' => $bath->id, 'operator_id' => $anyone->id]],
        ]);

        $response->assertStatus(201);
    }

    public function test_a_failure_creating_a_service_line_rolls_back_the_whole_booking(): void
    {
        $pet = $this->pet();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $bath = $this->service('Baño', 30);

        // Fuerza que la creación de la línea de servicio falle a mitad del store().
        // Se clona el dispatcher para no dejar el listener registrado en otros tests.
        $originalDispatcher = SpaBookingService::getEventDispatcher();
        $probe = clone $originalDispatcher;
        $probe->listen('eloquent.creating: '.SpaBookingService::class, static function () {
            throw new \RuntimeException('fallo simulado al crear la línea de servicio');
        });
        SpaBookingService::setEventDispatcher($probe);

        try {
            $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
                'pet_id' => $pet->id,
                'operator_id' => $operator->id,
                'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
                'duration_minutes' => 30,
                'services' => [['id' => $bath->id]],
            ]);
        } finally {
            SpaBookingService::setEventDispatcher($originalDispatcher);
        }

        $response->assertStatus(500);
        // La transacción revierte la cita: no queda ni el encabezado ni líneas huérfanas.
        $this->assertDatabaseCount('spa_bookings', 0);
        $this->assertDatabaseCount('spa_booking_services', 0);
    }

    public function test_offset_minutes_persist_per_line_and_extend_the_booking_duration(): void
    {
        $pet = $this->pet();
        $op = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'A', 'first_name' => 'A', 'is_active' => true]);
        $bath = $this->service('Baño', 30);
        $cut = $this->service('Corte', 30);

        // Baño 11:00–11:30, luego 20 min de espera, Corte 11:50–12:20.
        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $op->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [
                ['id' => $bath->id, 'operator_id' => $op->id, 'duration_minutes' => 30, 'offset_minutes' => 0],
                ['id' => $cut->id, 'operator_id' => $op->id, 'duration_minutes' => 30, 'offset_minutes' => 50],
            ],
        ]);

        $response->assertStatus(201);
        $booking = SpaBooking::firstOrFail();
        // Fin más lejano = 50 + 30 = 80 (no la suma 60).
        $this->assertSame(80, (int) $booking->duration_minutes);
        $this->assertSame(0, (int) $booking->services()->where('service_id', $bath->id)->value('scheduled_offset_minutes'));
        $this->assertSame(50, (int) $booking->services()->where('service_id', $cut->id)->value('scheduled_offset_minutes'));
        $response->assertJsonPath('services.1.start_time', '11:50');
    }

    public function test_offset_lets_the_second_operator_avoid_a_conflict_by_starting_later(): void
    {
        $pet = $this->pet();
        $a = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'A', 'first_name' => 'A', 'is_active' => true]);
        $b = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'B', 'first_name' => 'B', 'is_active' => true]);
        $bath = $this->service('Baño', 30);
        $cut = $this->service('Corte', 30);
        $start = now()->addDay()->setTime(11, 0);

        // B ocupado 11:30–12:00. Pegado, el Corte (2º servicio) caería 11:30–12:00 y chocaría.
        SpaBooking::create([
            'pet_id' => $pet->id, 'operator_id' => $b->id,
            'scheduled_at' => $start->copy()->addMinutes(30), 'duration_minutes' => 30,
            'status' => 'scheduled', 'total_estimated_price' => 0,
        ]);

        // Con 30 min de espera antes del Corte → arranca 12:00, sin choque.
        $ok = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $a->id,
            'scheduled_at' => $start->format('Y-m-d H:i:s'),
            'services' => [
                ['id' => $bath->id, 'operator_id' => $a->id, 'duration_minutes' => 30, 'offset_minutes' => 0],
                ['id' => $cut->id, 'operator_id' => $b->id, 'duration_minutes' => 30, 'offset_minutes' => 60],
            ],
        ]);
        $ok->assertStatus(201);

        // Sin espera (pegado) → 422 por choque en el segmento de B.
        $bad = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $a->id,
            'scheduled_at' => $start->format('Y-m-d H:i:s'),
            'services' => [
                ['id' => $bath->id, 'operator_id' => $a->id, 'duration_minutes' => 30, 'offset_minutes' => 0],
                ['id' => $cut->id, 'operator_id' => $b->id, 'duration_minutes' => 30, 'offset_minutes' => 30],
            ],
        ]);
        $bad->assertStatus(422);
    }

    public function test_a_per_line_operator_commitment_blocks_a_new_booking_and_a_cancel_frees_it(): void
    {
        $pet = $this->pet();
        $a = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'A', 'first_name' => 'A', 'is_active' => true]);
        $b = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'B', 'first_name' => 'B', 'is_active' => true]);
        $bath = $this->service('Baño', 30);
        $cut = $this->service('Corte', 30);
        $start = now()->addDay()->setTime(11, 0);

        // Cita 1: responsable A. B solo hace el 2º servicio, 11:30–12:00.
        $c1 = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $a->id,
            'scheduled_at' => $start->format('Y-m-d H:i:s'),
            'services' => [
                ['id' => $bath->id, 'operator_id' => $a->id, 'duration_minutes' => 30, 'offset_minutes' => 0],
                ['id' => $cut->id, 'operator_id' => $b->id, 'duration_minutes' => 30, 'offset_minutes' => 30],
            ],
        ]);
        $c1->assertStatus(201);
        $bLineId = collect($c1->json('services'))->firstWhere('operator_id', $b->id)['booking_service_id'];
        $c1Id = $c1->json('id');

        // Agendar a B a las 11:30 en OTRA cita → 422: su compromiso por línea lo bloquea.
        $blocked = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $b->id,
            'scheduled_at' => $start->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'services' => [['id' => $bath->id, 'operator_id' => $b->id, 'duration_minutes' => 30]],
        ]);
        $blocked->assertStatus(422);

        // Cancelar la línea de B en la cita 1 → su tiempo se libera.
        $this->withHeaders($this->authHeader())
            ->patchJson("/api/bookings/{$c1Id}/services/{$bLineId}", ['mark_cancelled' => true])
            ->assertStatus(200);

        $freed = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $b->id,
            'scheduled_at' => $start->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'services' => [['id' => $cut->id, 'operator_id' => $b->id, 'duration_minutes' => 30]],
        ]);
        $freed->assertStatus(201);
    }

    public function test_cancelling_the_whole_booking_frees_every_line(): void
    {
        $pet = $this->pet();
        $a = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'A', 'first_name' => 'A', 'is_active' => true]);
        $b = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'B', 'first_name' => 'B', 'is_active' => true]);
        $bath = $this->service('Baño', 30);
        $cut = $this->service('Corte', 30);
        $start = now()->addDay()->setTime(11, 0);

        $c1 = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $a->id,
            'scheduled_at' => $start->format('Y-m-d H:i:s'),
            'services' => [
                ['id' => $bath->id, 'operator_id' => $a->id, 'duration_minutes' => 30, 'offset_minutes' => 0],
                ['id' => $cut->id, 'operator_id' => $b->id, 'duration_minutes' => 30, 'offset_minutes' => 30],
            ],
        ]);
        $c1->assertStatus(201);

        $this->withHeaders($this->authHeader())
            ->patchJson('/api/bookings/'.$c1->json('id'), ['status' => 'cancelled'])
            ->assertStatus(200);

        // Ambos operadores quedan libres en sus tramos.
        foreach ([[$a->id, 0], [$b->id, 30]] as [$opId, $off]) {
            $r = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
                'pet_id' => $pet->id,
                'operator_id' => $opId,
                'scheduled_at' => $start->copy()->addMinutes($off)->format('Y-m-d H:i:s'),
                'services' => [['id' => $bath->id, 'operator_id' => $opId, 'duration_minutes' => 30]],
            ]);
            $r->assertStatus(201);
        }
    }
}
