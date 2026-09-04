<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\CreatesRestrictedOperatorUser;
use Tests\TestCase;

/**
 * SYNC-082 — la columna "Acciones" de la vista Día (AgUniInd) muestra chips de acción
 * rápida contextuales al estado de la cita: Iniciar / No asistió (un clic), Terminar
 * (abre el pop-up `#agendaQuickActions` con todas las opciones de cierre), Cobrar,
 * Reprogramar, Corregir fecha. "Cancelar" no vive aquí (necesita motivo obligatorio).
 * Todas las acciones de transición van tras `@can('editar agenda')`.
 *
 * Las aserciones buscan el atributo `class="agenda-chip agenda-chip--X"` completo, no el
 * fragmento `agenda-chip--X` — este último también aparece en el `<style>` de la página
 * (regla CSS) y no probaría que el chip se haya renderizado de verdad.
 */
class AgendaIndexQuickActionsTest extends TestCase
{
    use CreatesAdminUser;
    use CreatesRestrictedOperatorUser;
    use RefreshDatabase;

    private const CHIP_START = 'class="agenda-chip agenda-chip--start"';

    private const CHIP_NOSHOW = 'class="agenda-chip agenda-chip--noshow"';

    private const CHIP_DONE = 'class="agenda-chip agenda-chip--done"';

    private const CHIP_PAY = 'class="agenda-chip agenda-chip--pay"';

    private const CHIP_NEUTRAL = 'class="agenda-chip agenda-chip--neutral"';

    private const CHIP_GHOST = 'class="agenda-chip agenda-chip--ghost"';

    protected function setUp(): void
    {
        parent::setUp();
        // Hora fija a media mañana de un día entre semana — evita que los offsets
        // relativos (`now()->addHours(2)`, etc.) crucen la medianoche y saquen la
        // cita de la ventana "hoy" de la vista Día cuando la suite corre de noche.
        Carbon::setTestNow(Carbon::parse('2026-09-02 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function booking(string $status, Carbon $scheduledAt, int $durationMinutes = 30, float $price = 100): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Mascota-'.uniqid()]);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => $scheduledAt,
            'status' => $status,
            'duration_minutes' => $durationMinutes,
            'total_estimated_price' => $price,
        ]);
    }

    private function dayView(User $user)
    {
        return $this->actingAs($user)->get(route('agenda.index', ['status_touched' => 1]));
    }

    public function test_scheduled_row_shows_iniciar_and_no_asistio_chips(): void
    {
        $b = $this->booking('scheduled', now()->addHour());

        $res = $this->dayView($this->admin());

        $res->assertOk();
        $res->assertSee(self::CHIP_START, false);
        $res->assertSee(self::CHIP_NOSHOW, false);
        $res->assertSee('action="'.route('agenda.start', $b).'"', false);
        $res->assertSee('action="'.route('agenda.no-show', $b).'"', false);
        $res->assertSee('data-confirm="¿Marcar esta cita como No se presentó?"', false);
        // Retraso o no, una cita Programada se puede reprogramar.
        $res->assertSee('Reprogramar');
        $res->assertSee('href="'.route('agenda.edit', $b).'"', false);
        // No es una cita En proceso: no debe salir el chip Terminar.
        $res->assertDontSee(self::CHIP_DONE, false);
    }

    public function test_work_order_row_shows_terminar_chip_that_opens_the_popup(): void
    {
        $b = $this->booking('work_order', now()->subMinutes(5));

        $res = $this->dayView($this->admin());

        $res->assertOk();
        $res->assertSee(self::CHIP_DONE, false);
        $res->assertSee('data-bs-target="#agendaQuickActions"', false);
        $res->assertSee('data-unfulfillable-url="'.route('agenda.unfulfillable', $b).'"', false);
        // La transición de un clic no se hace desde la columna en work_order.
        $res->assertDontSee(self::CHIP_START, false);
    }

    public function test_completed_with_balance_shows_cobrar_chip(): void
    {
        $b = $this->booking('completed', now()->subHour(), price: 250);

        $res = $this->dayView($this->admin());

        $res->assertOk();
        $res->assertSee(self::CHIP_PAY, false);
        $res->assertSee(route('agenda.show', ['booking' => $b, 'cobrar' => 1]), false);
    }

    public function test_completed_without_balance_has_no_cobrar_chip(): void
    {
        $this->booking('completed', now()->subHour(), price: 0);

        $res = $this->dayView($this->admin());

        $res->assertOk();
        $res->assertDontSee(self::CHIP_PAY, false);
        $res->assertSee(self::CHIP_GHOST, false); // Detalle sigue estando
    }

    public function test_cancelled_row_shows_reprogramar_chip(): void
    {
        $b = $this->booking('cancelled', now()->subHour());

        $res = $this->dayView($this->admin());

        $res->assertOk();
        $res->assertSee(self::CHIP_NEUTRAL, false);
        $res->assertSee('Reprogramar');
        $res->assertSee('href="'.route('agenda.edit', $b).'"', false);
    }

    public function test_alert_not_started_row_still_gets_iniciar_and_no_asistio(): void
    {
        $this->booking('scheduled', now()->subHours(2));

        $res = $this->dayView($this->admin());

        $res->assertOk();
        $res->assertSee('No se ha iniciado'); // el badge de alerta
        $res->assertSee(self::CHIP_START, false);
        $res->assertSee(self::CHIP_NOSHOW, false);
        // Una cita retrasada todavía se puede reprogramar (sigue en `scheduled`).
        $res->assertSee('Reprogramar');
    }

    public function test_alert_overdue_work_order_gets_terminar_chip(): void
    {
        $this->booking('work_order', now()->subHours(3));

        $res = $this->dayView($this->admin());

        $res->assertOk();
        $res->assertSee('Sin cerrar'); // el badge de alerta
        $res->assertSee(self::CHIP_DONE, false);
    }

    public function test_alert_future_row_gets_corregir_fecha_chip(): void
    {
        $this->booking('work_order', now()->addHours(2));

        $res = $this->dayView($this->admin());

        $res->assertOk();
        $res->assertSee('Fecha inválida'); // el badge de alerta
        $res->assertSee('Corregir fecha');
        $res->assertSee(self::CHIP_NEUTRAL, false);
    }

    public function test_operator_without_editar_agenda_sees_no_action_chips_but_keeps_detalle(): void
    {
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Op', 'first_name' => 'Op', 'is_active' => true]);
        $b = $this->booking('scheduled', now()->addHour());
        $b->update(['operator_id' => $operator->id]);

        $user = $this->createOperatorUser(['ver agenda'], $operator);

        $res = $this->actingAs($user)->get(route('agenda.index', ['status_touched' => 1]));

        $res->assertOk();
        $res->assertDontSee(self::CHIP_START, false);
        $res->assertDontSee(self::CHIP_NOSHOW, false);
        $res->assertDontSee(self::CHIP_DONE, false);
        $res->assertSee(self::CHIP_GHOST, false); // Detalle
    }
}
