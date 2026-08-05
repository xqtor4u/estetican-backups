<?php

namespace Tests\Feature\Agenda;

use App\Models\Operator;
use App\Models\OperatorUnavailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class AgendaBlockedDisplayTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function operator(string $name = 'Jose'): Operator
    {
        return Operator::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    public function test_day_view_shows_blocked_banner(): void
    {
        $operator = $this->operator('Jose');
        $operator->unavailabilities()->create([
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 13:00:00',
            'reason' => 'Vacaciones',
        ]);

        $response = $this->actingAs($this->admin())->get('/agenda?date=2026-07-06&date_scope=custom');

        $response->assertOk();
        $response->assertSee('Operadores no disponibles hoy');
        $response->assertSee('Vacaciones');
    }

    public function test_day_view_hides_banner_without_blocks(): void
    {
        $this->operator();

        $response = $this->actingAs($this->admin())->get('/agenda?date=2026-07-06&date_scope=custom');

        $response->assertOk();
        $response->assertDontSee('Operadores no disponibles hoy');
    }

    public function test_week_view_shows_blocked_dot(): void
    {
        $operator = $this->operator();
        $operator->unavailabilities()->create([
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 13:00:00',
        ]);

        $response = $this->actingAs($this->admin())->get('/agenda?cal_view=week&date=2026-07-06');

        $response->assertOk();
        $response->assertSee('bandeja-calendar-dot--bloqueo', false);
    }

    public function test_month_view_shows_blocked_dot(): void
    {
        $operator = $this->operator();
        $operator->unavailabilities()->create([
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 13:00:00',
        ]);

        $response = $this->actingAs($this->admin())->get('/agenda?cal_view=month&date=2026-07-06');

        $response->assertOk();
        $response->assertSee('bandeja-calendar-dot--bloqueo', false);
    }
}
