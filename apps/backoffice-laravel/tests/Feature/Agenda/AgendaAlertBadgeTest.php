<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * La agenda web (AgUniInd) nunca mostraba ninguna señal de cita atípica —
 * a diferencia de la app móvil, donde ya existía el badge/punto ámbar
 * parpadeante. Verifica que el badge y su etiqueta aparezcan también aquí.
 */
class AgendaAlertBadgeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function booking(string $status, \Carbon\Carbon $scheduledAt, ?int $durationMinutes = 30): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Mascota-'.uniqid()]);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => $scheduledAt,
            'status' => $status,
            'duration_minutes' => $durationMinutes,
            'total_estimated_price' => 100,
        ]);
    }

    public function test_day_view_flags_a_scheduled_booking_that_was_never_started(): void
    {
        $this->booking('scheduled', now()->subHours(2));

        $response = $this->actingAs($this->admin())->get(route('agenda.index', ['status_touched' => 1]));

        $response->assertOk();
        $response->assertSee('agenda-alert-badge', false);
        $response->assertSee('No se ha iniciado');
    }

    public function test_day_view_does_not_flag_a_booking_within_the_grace_period(): void
    {
        $this->booking('scheduled', now()->subMinutes(5));

        $response = $this->actingAs($this->admin())->get(route('agenda.index'));

        $response->assertOk();
        // "agenda-alert-badge" en sí aparece siempre (es una regla CSS en el <style>
        // de la página) — lo que prueba que NO se aplicó a ninguna cita es la ausencia
        // de la etiqueta que solo se imprime cuando el badge sí se activa.
        $response->assertDontSee('No se ha iniciado');
        $response->assertDontSee('Sin cerrar');
        $response->assertDontSee('Fecha inválida');
    }

    public function test_week_view_flags_an_overdue_work_order(): void
    {
        $this->booking('work_order', now()->subHours(3), durationMinutes: 30);

        $response = $this->actingAs($this->admin())->get(route('agenda.index', ['cal_view' => 'week']));

        $response->assertOk();
        $response->assertSee('agenda-calendar-event-chip--alert', false);
    }

    public function test_month_view_flags_a_work_order_scheduled_in_the_future(): void
    {
        $this->booking('work_order', now()->addHours(2), durationMinutes: 30);

        $response = $this->actingAs($this->admin())->get(route('agenda.index', ['cal_view' => 'month']));

        $response->assertOk();
        $response->assertSee('agenda-calendar-event-chip--alert', false);
    }

    /**
     * El badge de Estado solo distinguía "activo" (verde, scheduled) vs "inactivo"
     * (gris, todo lo demás) — Completada/En proceso/No se presentó/No realizada
     * se veían todas igual, sin ningún color propio.
     */
    public function test_table_gives_each_closed_status_its_own_color(): void
    {
        $this->booking('completed', now()->startOfDay()->setTime(8, 0));
        $this->booking('no_show', now()->startOfDay()->setTime(9, 0));
        $this->booking('unfulfillable', now()->startOfDay()->setTime(9, 30));
        $this->booking('work_order', now()->subMinutes(10), durationMinutes: 30);

        $response = $this->actingAs($this->admin())->get(route('agenda.index', [
            'status_touched' => 1,
            'status' => ['completed', 'no_show', 'unfulfillable', 'work_order'],
        ]));

        $response->assertOk();
        $response->assertSee('agenda-status-badge--completed', false);
        $response->assertSee('agenda-status-badge--no-show', false);
        $response->assertSee('agenda-status-badge--unfulfillable', false);
        $response->assertSee('agenda-status-badge--work-order', false);
    }
}
