<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\DocumentSeries;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * AgUniInd (agenda/index.blade.php): en modo Semana/Mes los chips solo distinguían
 * SPA/Hotel + alerta atípica, no el resto de los estados de la cita (completada/en
 * proceso/no se presentó/no realizada/cancelada) — la tabla de Día sí los tiene desde
 * la sesión anterior. Tampoco se mostraba en ningún lado el order_folio (la referencia
 * única que ya se imprime en recibo/OT/presupuesto y ya se usa en la app móvil).
 */
class AgendaCalendarStatusAndFolioTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function bookingWithStatus(string $status, ?string $folio = null): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Mascota'.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30, 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => $status,
            'total_estimated_price' => 100,
            'order_folio' => $folio,
        ]);

        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => 100]);

        return $booking;
    }

    public function test_week_view_colors_a_completed_booking_chip_blue(): void
    {
        $booking = $this->bookingWithStatus('completed', 'OT-SPA-000042');

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['cal_view' => 'week', 'status_touched' => 1, 'date' => $booking->scheduled_at->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee('agenda-calendar-event-chip--completed', false);
        $response->assertSee('OT-SPA-000042', false);
    }

    public function test_week_view_colors_a_work_order_booking_chip_pink(): void
    {
        $booking = $this->bookingWithStatus('work_order');

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['cal_view' => 'week', 'date' => $booking->scheduled_at->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee('agenda-calendar-event-chip--work-order', false);
    }

    public function test_month_view_colors_a_no_show_booking_chip_red_and_puts_folio_in_the_title(): void
    {
        $booking = $this->bookingWithStatus('no_show', 'OT-SPA-000099');

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['cal_view' => 'month', 'status_touched' => 1, 'date' => $booking->scheduled_at->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee('agenda-calendar-event-chip--no-show', false);
        $response->assertSee('OT-SPA-000099', false);
    }

    public function test_day_table_shows_the_order_folio_next_to_the_pet_name(): void
    {
        $booking = $this->bookingWithStatus('scheduled', 'OT-SPA-000007');

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $response->assertOk();
        $response->assertSee('OT-SPA-000007');
    }

    public function test_day_table_does_not_break_when_the_booking_has_no_folio_yet(): void
    {
        $this->bookingWithStatus('scheduled', null);

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $response->assertOk();
    }
}
