<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\OperatorWeeklySchedule;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * `GET /agenda/next-slot` — busca el primer día/hora hacia adelante donde una cita de N
 * minutos cabe entera para el operador (horario semanal ∩ horario del negocio, sin pisar
 * citas). `allow_other_qualified` amplía a operadores calificados y devuelve el más pronto.
 */
class AgendaNextSlotTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Miércoles a media mañana — lejos de fin de semana y de la medianoche.
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00'));
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

    private function operator(string $name = 'Jose'): Operator
    {
        return Operator::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    private function bookFullDay(Operator $op, string $date): SpaBooking
    {
        // 09:00–19:00 tapado con una sola cita larga.
        $client = Client::create(['first_name' => 'X', 'apellido_paterno' => 'Y'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'P'.uniqid()]);

        return SpaBooking::create([
            'pet_id' => $pet->id, 'operator_id' => $op->id,
            'scheduled_at' => $date.' 09:00:00', 'status' => 'scheduled',
            'duration_minutes' => 600, 'total_estimated_price' => 100,
        ]);
    }

    private function ask(array $params): TestResponse
    {
        return $this->actingAs($this->admin())->getJson('/agenda/next-slot?'.http_build_query($params));
    }

    public function test_free_operator_gets_a_slot_at_business_open_on_the_from_date(): void
    {
        $op = $this->operator();

        $res = $this->ask(['operator_id' => $op->id, 'duration_minutes' => 60, 'from' => '2026-09-03']);

        $res->assertOk()->assertJson(['found' => true, 'date' => '2026-09-03', 'time' => '09:00', 'operator_id' => $op->id]);
    }

    public function test_when_the_first_day_is_full_it_returns_the_next_day(): void
    {
        $op = $this->operator();
        $this->bookFullDay($op, '2026-09-03');

        $res = $this->ask(['operator_id' => $op->id, 'duration_minutes' => 60, 'from' => '2026-09-03']);

        $res->assertOk()->assertJson(['found' => true, 'date' => '2026-09-04', 'time' => '09:00']);
        $this->assertSame(1, $res->json('days_ahead'));
    }

    public function test_it_slots_after_an_existing_booking_the_same_day(): void
    {
        $op = $this->operator();
        $client = Client::create(['first_name' => 'A', 'apellido_paterno' => 'B']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Q']);
        SpaBooking::create([
            'pet_id' => $pet->id, 'operator_id' => $op->id,
            'scheduled_at' => '2026-09-03 09:00:00', 'status' => 'scheduled',
            'duration_minutes' => 90, 'total_estimated_price' => 100,
        ]);

        $res = $this->ask(['operator_id' => $op->id, 'duration_minutes' => 60, 'from' => '2026-09-03']);

        $res->assertOk()->assertJson(['found' => true, 'date' => '2026-09-03', 'time' => '10:30']);
    }

    public function test_it_skips_days_the_operator_does_not_work(): void
    {
        $op = $this->operator();
        // Solo labora los viernes (dayOfWeek 5). 2026-09-03 es jueves → salta a 2026-09-04 (viernes).
        OperatorWeeklySchedule::create(['operator_id' => $op->id, 'day_of_week' => 5, 'start_time' => '10:00', 'end_time' => '16:00']);

        $res = $this->ask(['operator_id' => $op->id, 'duration_minutes' => 60, 'from' => '2026-09-03']);

        $res->assertOk()->assertJson(['found' => true, 'date' => '2026-09-04', 'time' => '10:00']);
    }

    public function test_returns_not_found_when_nothing_fits_in_the_window(): void
    {
        $op = $this->operator();
        foreach (['2026-09-03', '2026-09-04', '2026-09-05'] as $d) {
            $this->bookFullDay($op, $d);
        }

        $res = $this->ask(['operator_id' => $op->id, 'duration_minutes' => 60, 'from' => '2026-09-03', 'days' => 3]);

        $res->assertOk()->assertJson(['found' => false, 'searched_days' => 3]);
    }

    public function test_allow_other_qualified_returns_the_substitute_with_the_earliest_slot(): void
    {
        $svc = Service::create(['code' => 'BATH'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 60, 'is_active' => true, 'open_to_all_operators' => true]);
        $busyOp = $this->operator('Ocupado');
        $freeOp = $this->operator('Libre');
        $this->bookFullDay($busyOp, '2026-09-03');

        $res = $this->ask([
            'operator_id' => $busyOp->id,
            'duration_minutes' => 60,
            'from' => '2026-09-03',
            'days' => 1,
            'allow_other_qualified' => 1,
            'service_ids' => [$svc->id],
        ]);

        $res->assertOk()->assertJson(['found' => true, 'date' => '2026-09-03', 'time' => '09:00', 'operator_id' => $freeOp->id]);
    }

    /**
     * SYNC-094 — `exclude_booking_id` se validaba en `nextSlot()` (AgSpaEdi lo manda al buscar
     * "el próximo hueco" para reprogramar) pero nunca se usaba: la cita que se está editando
     * contaba como "ocupada" contra sí misma, así que "buscar el próximo hueco" podía saltarse
     * de largo el día en que la cita ya vive, aunque de sobra hubiera lugar quitándola de en
     * medio primero (que es justo lo que se va a hacer al reprogramarla).
     */
    public function test_excludes_the_booking_being_edited_from_its_own_full_day_block(): void
    {
        $op = $this->operator();
        $booking = $this->bookFullDay($op, '2026-09-03');

        $res = $this->ask(['operator_id' => $op->id, 'duration_minutes' => 60, 'from' => '2026-09-03', 'exclude_booking_id' => $booking->id]);

        $res->assertOk()->assertJson(['found' => true, 'date' => '2026-09-03', 'time' => '09:00']);
        $this->assertSame(0, $res->json('days_ahead'));
    }
}
