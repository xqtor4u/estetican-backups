<?php

namespace Tests\Feature\Agenda;

use App\Domain\Planning\Services\ServiceLineActionService;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-088 — "operador por asignar" por línea de servicio. La columna
 * `spa_booking_services.operator_id` ya es nullable; el corte es de lógica: al agendar se
 * puede dejar una línea "por asignar" (satélite), el responsable sale de una línea con
 * operador real, la validación secuencial salta las líneas sin operador, y no se puede
 * iniciar una línea sin operador (piso la asigna primero).
 */
class AgendaOperatorPorAsignarTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function operator(string $name = 'Jose'): Operator
    {
        return Operator::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function service(string $name = 'Baño'): Service
    {
        return Service::create(['code' => 'S'.uniqid(), 'name' => $name, 'type' => 'spa', 'price' => 200, 'duration_minutes' => 30, 'is_active' => true, 'open_to_all_operators' => true]);
    }

    public function test_a_line_can_be_scheduled_as_por_asignar_while_another_carries_the_operator(): void
    {
        $pet = $this->pet();
        $anchor = $this->service('Cirugía');
        $satellite = $this->service('Baño previo');
        $surgeon = $this->operator('Cirujano');
        $at = now()->addDay()->setTime(11, 0);

        $res = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'scheduled_at' => $at->format('Y-m-d H:i:s'),
            'services' => [$satellite->id, $anchor->id],
            'service_operators' => [$satellite->id => 'pending', $anchor->id => $surgeon->id],
        ]);

        $res->assertRedirect(route('agenda.index'));

        $booking = SpaBooking::latest('id')->first();
        $this->assertSame($surgeon->id, $booking->operator_id, 'el responsable sale de la línea con operador real');
        $this->assertDatabaseHas('spa_booking_services', ['spa_booking_id' => $booking->id, 'service_id' => $anchor->id, 'operator_id' => $surgeon->id]);
        $this->assertDatabaseHas('spa_booking_services', ['spa_booking_id' => $booking->id, 'service_id' => $satellite->id, 'operator_id' => null]);
    }

    public function test_all_lines_por_asignar_is_rejected(): void
    {
        $pet = $this->pet();
        $s1 = $this->service('A');
        $s2 = $this->service('B');

        $res = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'services' => [$s1->id, $s2->id],
            'service_operators' => [$s1->id => 'pending', $s2->id => 'pending'],
        ]);

        $res->assertSessionHas('error');
        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_sequential_validation_does_not_false_conflict_on_a_por_asignar_line(): void
    {
        $pet = $this->pet();
        $anchor = $this->service('Cirugía');
        $satellite = $this->service('Secado');
        $surgeon = $this->operator('Cirujano');
        $busyOp = $this->operator('Ocupado');
        $at = now()->addDay()->setTime(11, 0);

        // `busyOp` tiene una cita justo en el segundo segmento (donde caería el satélite),
        // pero el satélite va "por asignar" → no se valida a nadie ahí.
        $otherPet = $this->pet();
        SpaBooking::create([
            'pet_id' => $otherPet->id, 'operator_id' => $busyOp->id,
            'scheduled_at' => $at->copy()->addMinutes(30), 'status' => 'scheduled',
            'duration_minutes' => 30, 'total_estimated_price' => 100,
        ]);

        $res = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'scheduled_at' => $at->format('Y-m-d H:i:s'),
            'services' => [$anchor->id, $satellite->id],
            'service_operators' => [$anchor->id => $surgeon->id, $satellite->id => 'pending'],
        ]);

        $res->assertRedirect(route('agenda.index'));
    }

    public function test_cannot_start_a_line_without_an_operator(): void
    {
        $pet = $this->pet();
        $svc = $this->service();
        $booking = SpaBooking::create([
            'pet_id' => $pet->id, 'scheduled_at' => now()->addDay()->setTime(11, 0),
            'status' => 'work_order', 'duration_minutes' => 30, 'total_estimated_price' => 200,
        ]);
        $line = $booking->services()->create(['service_id' => $svc->id, 'current_price' => 200, 'operator_id' => null]);

        $err = app(ServiceLineActionService::class)->apply($booking->fresh(), $line->fresh(), ['mark_started' => true]);
        $this->assertStringContainsString('Asigna un operador', (string) $err);

        // Con operador en la misma acción sí arranca.
        $op = $this->operator();
        $err2 = app(ServiceLineActionService::class)->apply($booking->fresh(), $line->fresh(), ['mark_started' => true, 'operator_id' => $op->id]);
        $this->assertNull($err2);
        $this->assertNotNull($line->fresh()->started_at);
        $this->assertSame($op->id, $line->fresh()->operator_id);
    }

    public function test_agenda_index_exposes_the_line_operator_as_null_for_a_por_asignar_line(): void
    {
        $pet = $this->pet();
        $anchor = $this->service('Cirugía');
        $satellite = $this->service('Baño');
        $surgeon = $this->operator('Cirujano');
        $booking = SpaBooking::create([
            'pet_id' => $pet->id, 'operator_id' => $surgeon->id,
            'scheduled_at' => now()->setTime(11, 0), 'status' => 'work_order',
            'duration_minutes' => 60, 'total_estimated_price' => 400,
        ]);
        $booking->services()->create(['service_id' => $anchor->id, 'current_price' => 300, 'operator_id' => $surgeon->id]);
        $booking->services()->create(['service_id' => $satellite->id, 'current_price' => 100, 'operator_id' => null]);

        $res = $this->actingAs($this->admin())->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $res->assertOk();
        $res->assertSee('&quot;operator&quot;:null', false);   // el satélite en el data-services
    }
}
