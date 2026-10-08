<?php

namespace Tests\Feature\Series;

use App\Domain\Planning\Series\BookingSeriesService;
use App\Domain\Planning\Series\RecurrenceRule;
use App\Models\Client;
use App\Models\NonWorkingDay;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBookingSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * ZEUS-047 Fase 1 — pantallas: catálogo de días inhábiles y el punto de serie recurrente en
 * la agenda (lista, día, semana, mes) y en el detalle de la cita.
 */
class BookingSeriesScreensTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-07 08:00:00'));
        $this->admin = $this->createAdminUser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function series(): SpaBookingSeries
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $service = Service::create(['code' => 'BC01', 'name' => 'Baño y corte', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60, 'open_to_all_operators' => true]);
        NonWorkingDay::create(['date' => '2026-11-02', 'reason' => 'Día de muertos']);

        $this->actingAs($this->admin);

        return app(BookingSeriesService::class)->create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'lines' => [['service_id' => $service->id, 'service_name' => $service->name, 'operator_id' => $operator->id, 'duration_minutes' => 60, 'price' => 250]],
        ], RecurrenceRule::monthlyWeekday(1, Carbon::MONDAY), Carbon::parse('2026-10-12 12:00'), Carbon::parse('2027-01-31'));
    }

    public function test_admin_registers_and_deletes_a_non_working_day(): void
    {
        $this->actingAs($this->admin)
            ->post(route('non-working-days.store'), ['date' => '2026-12-25', 'reason' => 'Navidad'])
            ->assertRedirect(route('non-working-days.index'));

        $this->get(route('non-working-days.index'))->assertOk()->assertSee('Navidad')->assertSee('Todas las sucursales');

        $this->post(route('non-working-days.store'), ['date' => '2026-12-25', 'reason' => 'Otra vez'])->assertSessionHas('error');
        $this->assertDatabaseCount('non_working_days', 1);

        $this->delete(route('non-working-days.destroy', NonWorkingDay::first()))->assertRedirect(route('non-working-days.index'));
        $this->assertDatabaseCount('non_working_days', 0);
    }

    public function test_non_working_days_require_branch_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('non-working-days.index'))->assertForbidden();
        $this->actingAs($user)->post(route('non-working-days.store'), ['date' => '2026-12-25', 'reason' => 'Navidad'])->assertForbidden();
    }

    public function test_series_bookings_show_the_series_dot_in_every_agenda_view(): void
    {
        $series = $this->series();
        // 1er lunes de noviembre (02/11) es festivo → recorrida al martes 03/11 12:00.
        $moved = $series->bookings->firstWhere('series_original_at', '!=', null);
        $this->assertSame('2026-11-03 12:00', $moved->scheduled_at->format('Y-m-d H:i'));

        foreach (['week', 'month'] as $view) {
            $this->get(route('agenda.index', ['cal_view' => $view, 'date' => '2026-11-03']))
                ->assertOk()
                ->assertSee('agenda-series-dot agenda-series-dot--pending', false)
                ->assertSee('agenda-calendar-event-chip--tentative', false);
        }

        $this->get(route('agenda.index', ['date_scope' => 'custom', 'date' => '2026-11-03']))
            ->assertOk()
            ->assertSee('agenda-series-dot', false)
            ->assertSee('bi-shuffle', false);

        $this->get(route('agenda.show', $moved))
            ->assertOk()
            ->assertSee('Serie recurrente')
            ->assertSee('1er lunes de cada mes')
            ->assertSee('restan 3')
            ->assertSee('Pre-programada')
            ->assertSee('Día de muertos');
    }

    /** Regla de Tomas: aunque la serie esté activa, cada cita va tenue hasta que se fija. */
    public function test_only_pinned_bookings_show_a_solid_dot_even_in_an_active_series(): void
    {
        $series = $this->series();
        $series->update(['status' => SpaBookingSeries::STATUS_ACTIVE]);
        $week = route('agenda.index', ['cal_view' => 'week', 'date' => '2026-12-07']);

        $this->get($week)
            ->assertOk()
            ->assertSee('agenda-series-dot agenda-series-dot--pending', false)
            ->assertSee('agenda-calendar-event-chip--tentative', false);

        $this->post(route('agenda.series.pin', $series->bookings->firstWhere(fn ($b) => $b->scheduled_at->format('Y-m-d') === '2026-12-07')));

        $this->get($week)
            ->assertOk()
            ->assertSee('class="agenda-series-dot"', false)
            ->assertDontSee('agenda-calendar-event-chip--tentative', false);
    }
}
