<?php

namespace Tests\Feature\Series;

use App\Domain\Planning\Series\BookingSeriesService;
use App\Domain\Planning\Series\RecurrenceRule;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingSeries;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * ZEUS-047 — botón "Fijar": confirma una sola cita de una serie pre-programada (punto sólido,
 * recibe recordatorio) sin confirmar la serie completa. Y los días cerrados recorren la serie.
 */
class PinSeriesBookingTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-07 08:00:00')); // miércoles
        $this->admin = $this->createAdminUser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function series(RecurrenceRule $rule, string $start, string $until): SpaBookingSeries
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $service = Service::create(['code' => 'BC01', 'name' => 'Baño', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60, 'open_to_all_operators' => true]);

        $this->actingAs($this->admin);

        return app(BookingSeriesService::class)->create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'lines' => [['service_id' => $service->id, 'service_name' => $service->name, 'operator_id' => $operator->id, 'duration_minutes' => 60, 'price' => 250]],
        ], $rule, Carbon::parse($start), Carbon::parse($until));
    }

    public function test_pin_confirms_only_that_booking(): void
    {
        $series = $this->series(RecurrenceRule::everyNDays(7), '2026-10-14 11:00', '2026-10-28');
        [$first, $second] = $series->bookings->all();

        $this->get(route('agenda.show', $first))->assertOk()->assertSee('Fijar');

        $this->post(route('agenda.series.pin', $first))
            ->assertRedirect(route('agenda.show', $first))
            ->assertSessionHas('success');

        $this->assertNotNull($first->fresh()->series_confirmed_at);
        $this->assertFalse($first->fresh()->isSeriesTentative());
        $this->assertTrue($second->fresh()->isSeriesTentative(), 'las demás siguen pre-programadas');
        $this->assertSame(SpaBookingSeries::STATUS_PENDING_REVIEW, $series->fresh()->status);

        $this->get(route('agenda.show', $first))->assertOk()->assertSee('Fijada')->assertDontSee('bi-pin-angle-fill me-1', false);
        $this->get(route('agenda.index', ['cal_view' => 'week', 'date' => '2026-10-14']))
            ->assertOk()
            ->assertSee('class="agenda-series-dot"', false);
    }

    public function test_bookings_of_an_active_series_also_need_pinning_but_only_once(): void
    {
        $series = $this->series(RecurrenceRule::everyNDays(7), '2026-10-14 11:00', '2026-10-14');
        $series->update(['status' => SpaBookingSeries::STATUS_ACTIVE]);
        $booking = $series->bookings->first();

        $this->assertTrue($booking->fresh()->isSeriesTentative(), 'serie activa no confirma sus citas');

        $this->post(route('agenda.series.pin', $booking))->assertSessionHas('success');
        $this->assertNotNull($booking->fresh()->series_confirmed_at);

        $this->post(route('agenda.series.pin', $booking))->assertSessionHas('error');
    }

    public function test_a_loose_booking_cannot_be_pinned(): void
    {
        $series = $this->series(RecurrenceRule::everyNDays(7), '2026-10-14 11:00', '2026-10-14');
        $loose = SpaBooking::create(['pet_id' => $series->pet_id, 'operator_id' => $series->template['operator_id'], 'scheduled_at' => '2026-10-20 11:00:00', 'duration_minutes' => 30, 'status' => 'scheduled', 'total_estimated_price' => 0]);

        $this->post(route('agenda.series.pin', $loose))->assertSessionHas('error');
        $this->assertNull($loose->fresh()->series_confirmed_at);
    }

    public function test_pin_requires_edit_permission(): void
    {
        $booking = $this->series(RecurrenceRule::everyNDays(7), '2026-10-14 11:00', '2026-10-14')->bookings->first();

        $this->actingAs(User::factory()->create())->post(route('agenda.series.pin', $booking))->assertForbidden();
        $this->assertNull($booking->fresh()->series_confirmed_at);
    }

    public function test_a_closed_weekday_moves_the_series_booking_to_the_next_open_day(): void
    {
        app(SystemSettings::class)->saveFields('operations', ['booking_open_sunday' => false]);

        // Cada 4 días desde el miércoles 07/10 a las 11:00 → domingo 11/10 cae en día cerrado.
        $series = $this->series(RecurrenceRule::everyNDays(4), '2026-10-07 11:00', '2026-10-11');

        $sunday = $series->bookings->firstWhere('series_original_at', '!=', null);
        $this->assertSame('2026-10-12 11:00', $sunday->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('El negocio no abre los domingos.', $sunday->series_move_reason);
    }

    /** Tomas: el botón vive en la Agenda unificada (AgUniInd) — un clic y vuelve a la misma pantalla. */
    public function test_pin_button_is_on_the_unified_agenda_and_returns_there(): void
    {
        $series = $this->series(RecurrenceRule::everyNDays(7), '2026-10-14 11:00', '2026-10-21');
        [$first, $second] = $series->bookings->all();
        $agendaUrl = route('agenda.index', ['date_scope' => 'all']);

        $this->get($agendaUrl)
            ->assertOk()
            ->assertSee(route('agenda.series.pin', $first), false)
            ->assertSee(route('agenda.series.pin', $second), false);

        $this->from($agendaUrl)->post(route('agenda.series.pin', $first))
            ->assertRedirect($agendaUrl)
            ->assertSessionHas('success', 'Cita fijada: Luka · 14/10/2026 11:00 queda confirmada.');

        $this->get($agendaUrl)
            ->assertOk()
            ->assertDontSee(route('agenda.series.pin', $first), false)
            ->assertSee(route('agenda.series.pin', $second), false);
    }

    /** Sin navegar: la Agenda avisa cuántas hay por fijar y un clic filtra solo esas. */
    public function test_agenda_banner_counts_and_filters_bookings_to_pin(): void
    {
        $series = $this->series(RecurrenceRule::everyNDays(7), '2026-10-14 11:00', '2026-10-28');
        $loose = SpaBooking::create(['pet_id' => $series->pet_id, 'operator_id' => $series->template['operator_id'], 'scheduled_at' => '2026-10-07 15:00:00', 'duration_minutes' => 30, 'status' => 'scheduled', 'total_estimated_price' => 0]);
        $this->post(route('agenda.series.pin', $series->bookings->first()));

        $this->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('2 citas recurrentes por fijar')
            ->assertSee(route('agenda.index', ['por_fijar' => 1]), false);

        $this->get(route('agenda.index', ['por_fijar' => 1]))
            ->assertOk()
            ->assertSee('Mostrando solo citas recurrentes por fijar (2)')
            ->assertSee(route('agenda.series.pin', $series->bookings[1]), false)
            ->assertSee(route('agenda.series.pin', $series->bookings[2]), false)
            ->assertDontSee(route('agenda.show', $loose), false);
    }
}
