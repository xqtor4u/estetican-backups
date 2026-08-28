<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Carbon\Carbon;
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
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function booking(string $status, Carbon $scheduledAt, ?int $durationMinutes = 30): SpaBooking
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

    /**
     * El tag de estado de una cita Programada / En proceso es un botón que abre el
     * pop-up de acciones rápidas (Iniciar cita / Terminar y cobrar / ...). Las citas
     * cerradas mantienen el <span> estático, sin acciones.
     */
    public function test_scheduled_and_work_order_status_tags_are_quick_action_triggers(): void
    {
        // `date_scope=all` para que el test no dependa de la hora de reloj (now()+3h cruza la
        // medianoche cerca de las 21:00 y el filtro "hoy" dejaba fuera la cita programada).
        $scheduled = $this->booking('scheduled', now()->addHours(3));
        // work_order "en curso normal": empezó hace poco y sigue dentro de su ventana
        // (un work_order en el futuro o vencido lleva alerta y NO ofrece acciones rápidas).
        $workOrder = $this->booking('work_order', now()->subMinutes(10), durationMinutes: 30);
        $completed = $this->booking('completed', now()->startOfDay()->setTime(8, 0));

        $response = $this->actingAs($this->admin())->get(route('agenda.index', [
            'status_touched' => 1,
            'date_scope' => 'all',
            'status' => ['scheduled', 'work_order', 'completed'],
        ]));

        $response->assertOk();
        $response->assertSee('id="agendaQuickActions"', false);
        $response->assertSee('agenda-status-trigger', false);
        $response->assertSee(route('agenda.start', $scheduled), false);
        $response->assertSee(route('agenda.start', $workOrder), false);
        $response->assertDontSee(route('agenda.start', $completed), false);
    }

    public function test_each_service_pill_is_a_trigger_for_that_line_on_an_open_booking(): void
    {
        $wo = $this->booking('work_order', now()->subMinutes(10));
        $bath = Service::create(['code' => 'B'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30, 'is_active' => true]);
        $cut = Service::create(['code' => 'C'.uniqid(), 'name' => 'Corte', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30, 'is_active' => true]);
        $lineA = $wo->services()->create(['service_id' => $bath->id, 'current_price' => 100]);
        $lineB = $wo->services()->create(['service_id' => $cut->id, 'current_price' => 100]);

        $completed = $this->booking('completed', now()->startOfDay()->setTime(8, 0));
        $completed->services()->create(['service_id' => $bath->id, 'current_price' => 100]);

        $response = $this->actingAs($this->admin())->get(route('agenda.index', [
            'status_touched' => 1,
            'date_scope' => 'all',
            'status' => ['work_order', 'completed'],
        ]));

        $response->assertOk();
        $response->assertSee('agenda-service-trigger', false);
        $response->assertSee('data-mode="service"', false);
        // La plantilla de URL por línea apunta al endpoint de acciones por servicio.
        $response->assertSee(route('agenda.services.update', ['booking' => $wo, 'line' => '__LINE__']), false);
        // Una cita cerrada NO vuelve sus servicios clicables.
        $response->assertDontSee(route('agenda.services.update', ['booking' => $completed, 'line' => '__LINE__']), false);
    }
}
