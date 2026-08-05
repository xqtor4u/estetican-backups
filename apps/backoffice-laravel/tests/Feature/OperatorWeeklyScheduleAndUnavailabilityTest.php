<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\OperatorUnavailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class OperatorWeeklyScheduleAndUnavailabilityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function operator(): Operator
    {
        return Operator::create([
            'code' => 'OP-'.uniqid(),
            'first_name' => 'Laura',
            'apellido_paterno' => 'Campos',
            'name' => 'Laura Campos',
            'is_active' => true,
        ]);
    }

    public function test_create_page_renders_weekly_schedule_block_without_existing_operator(): void
    {
        $response = $this->actingAs($this->admin())->get(route('operators.create'));

        $response->assertOk();
        $response->assertSee('Horario de trabajo semanal');
        $response->assertDontSee('Bloqueos de no disponibilidad');
    }

    public function test_create_page_prefills_weekly_schedule_with_business_hours(): void
    {
        $response = $this->actingAs($this->admin())->get(route('operators.create'));
        $content = $response->getContent();

        $response->assertOk();
        // Horario general por default (sin SystemSettings capturado): 09:00-19:00.
        $this->assertSame(7, substr_count($content, 'value="09:00"'));
        $this->assertSame(7, substr_count($content, 'value="19:00"'));
        // Los 7 días + el switch "Operador activo" quedan marcados por default.
        $this->assertSame(8, substr_count($content, 'checked>'));
    }

    public function test_edit_page_does_not_default_days_when_operator_already_has_a_partial_schedule(): void
    {
        $operator = $this->operator();
        $operator->weeklySchedules()->create([
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $response = $this->actingAs($this->admin())->get(route('operators.edit', $operator));
        $content = $response->getContent();

        $response->assertOk();
        $this->assertSame(1, substr_count($content, 'value="10:00"'));
        $this->assertSame(1, substr_count($content, 'value="14:00"'));
        // Solo lunes (ya configurado) + el switch "Operador activo" quedan marcados, no los otros 6 días.
        $this->assertSame(2, substr_count($content, 'checked>'));
    }

    public function test_edit_page_renders_weekly_schedule_and_unavailability_blocks(): void
    {
        $operator = $this->operator();
        $operator->unavailabilities()->create([
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-10 23:59:59',
            'reason' => 'Vacaciones',
        ]);

        $response = $this->actingAs($this->admin())->get(route('operators.edit', $operator));

        $response->assertOk();
        $response->assertSee('Horario de trabajo semanal');
        $response->assertSee('Bloqueos de no disponibilidad');
        $response->assertSee('Vacaciones');
        $response->assertSee('name="weekly_schedule[0][enabled]"', false);
    }

    public function test_update_creates_weekly_schedule_rows(): void
    {
        $operator = $this->operator();

        $response = $this->actingAs($this->admin())->put(route('operators.update', $operator), [
            'code' => $operator->code,
            'first_name' => $operator->first_name,
            'apellido_paterno' => $operator->apellido_paterno,
            'weekly_schedule' => [
                1 => ['enabled' => '1', 'start_time' => '09:00', 'end_time' => '14:00'],
                6 => ['enabled' => '1', 'start_time' => '09:00', 'end_time' => '13:00'],
            ],
        ]);

        $response->assertRedirect(route('operators.edit', $operator));
        $this->assertDatabaseHas('operator_weekly_schedules', [
            'operator_id' => $operator->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '14:00:00',
        ]);
        $this->assertDatabaseHas('operator_weekly_schedules', [
            'operator_id' => $operator->id,
            'day_of_week' => 6,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);
    }

    public function test_update_replaces_previous_weekly_schedule(): void
    {
        $operator = $this->operator();

        $this->actingAs($this->admin())->put(route('operators.update', $operator), [
            'code' => $operator->code,
            'first_name' => $operator->first_name,
            'apellido_paterno' => $operator->apellido_paterno,
            'weekly_schedule' => [
                1 => ['enabled' => '1', 'start_time' => '09:00', 'end_time' => '14:00'],
            ],
        ]);

        $this->actingAs($this->admin())->put(route('operators.update', $operator), [
            'code' => $operator->code,
            'first_name' => $operator->first_name,
            'apellido_paterno' => $operator->apellido_paterno,
            'weekly_schedule' => [
                2 => ['enabled' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
            ],
        ]);

        $this->assertDatabaseMissing('operator_weekly_schedules', [
            'operator_id' => $operator->id,
            'day_of_week' => 1,
        ]);
        $this->assertDatabaseHas('operator_weekly_schedules', [
            'operator_id' => $operator->id,
            'day_of_week' => 2,
            'start_time' => '10:00:00',
            'end_time' => '16:00:00',
        ]);
    }

    public function test_update_rejects_end_time_before_start_time(): void
    {
        $operator = $this->operator();

        $response = $this->actingAs($this->admin())->put(route('operators.update', $operator), [
            'code' => $operator->code,
            'first_name' => $operator->first_name,
            'apellido_paterno' => $operator->apellido_paterno,
            'weekly_schedule' => [
                1 => ['enabled' => '1', 'start_time' => '14:00', 'end_time' => '09:00'],
            ],
        ]);

        $response->assertSessionHasErrors('weekly_schedule.1.end_time');
        $this->assertDatabaseCount('operator_weekly_schedules', 0);
    }

    public function test_can_register_unavailability(): void
    {
        $operator = $this->operator();

        $response = $this->actingAs($this->admin())->post(route('operators.unavailabilities.store', $operator), [
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-10 23:59:59',
            'reason' => 'Vacaciones',
        ]);

        $response->assertRedirect(route('operators.edit', $operator));
        $this->assertDatabaseHas('operator_unavailabilities', [
            'operator_id' => $operator->id,
            'reason' => 'Vacaciones',
        ]);
    }

    public function test_can_delete_unavailability(): void
    {
        $operator = $this->operator();
        $unavailability = $operator->unavailabilities()->create([
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-10 23:59:59',
        ]);

        $response = $this->actingAs($this->admin())->delete(route('operators.unavailabilities.destroy', [$operator, $unavailability]));

        $response->assertRedirect(route('operators.edit', $operator));
        $this->assertDatabaseMissing('operator_unavailabilities', ['id' => $unavailability->id]);
    }

    public function test_cannot_delete_unavailability_of_another_operator(): void
    {
        $operator = $this->operator();
        $otherOperator = $this->operator();
        $unavailability = $otherOperator->unavailabilities()->create([
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-10 23:59:59',
        ]);

        $response = $this->actingAs($this->admin())->delete(route('operators.unavailabilities.destroy', [$operator, $unavailability]));

        $response->assertNotFound();
        $this->assertDatabaseHas('operator_unavailabilities', ['id' => $unavailability->id]);
    }
}
