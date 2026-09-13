<?php

namespace Tests\Feature\Agenda;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * `agenda.check-availability` acepta `resource_id` y devuelve si la jaula/recurso está
 * libre en ese horario (contando el buffer de limpieza) + sus ventanas ocupadas del día,
 * para que AgSpaCre pueda avisar al seleccionar una jaula ocupada.
 */
class AgendaResourceAvailabilityTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function cage(string $code = 'A1'): Resource
    {
        $branch = Branch::create(['code' => 'MTY-'.$code, 'name' => 'Sucursal', 'is_active' => true]);

        return Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => $code,
            'name' => 'Jaula '.$code,
            'administrative_status' => 'active',
            'operational_status' => 'available',
        ]);
    }

    private function occupy(Resource $cage, string $start, string $end): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Otro']);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => $start,
            'status' => 'scheduled',
            'duration_minutes' => 60,
            'total_estimated_price' => 100,
        ]);

        ResourceAllocation::create([
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => $start,
            'ends_at' => $end,
            'source_type' => $booking->getMorphClass(),
            'source_id' => $booking->id,
        ]);
    }

    private function ask(array $params): TestResponse
    {
        return $this->actingAs($this->admin())
            ->getJson('/agenda/check-availability?'.http_build_query($params));
    }

    public function test_no_resource_key_when_resource_id_is_omitted(): void
    {
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $res = $this->ask([
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $res->assertOk();
        $this->assertArrayNotHasKey('resource', $res->json());
    }

    public function test_reports_a_cage_as_free_when_nothing_overlaps(): void
    {
        $operator = Operator::create(['code' => 'OP2', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $cage = $this->cage('B1');

        $res = $this->ask([
            'operator_id' => $operator->id,
            'resource_id' => $cage->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
        ]);

        $res->assertOk();
        $res->assertJsonPath('resource.available', true);
        $res->assertJsonPath('resource.busy', []);
        $this->assertStringContainsString('B1', $res->json('resource.name'));
    }

    public function test_reports_a_cage_as_occupied_when_the_block_overlaps_an_allocation(): void
    {
        $operator = Operator::create(['code' => 'OP3', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $cage = $this->cage('C1');
        $day = now()->addDay()->format('Y-m-d');
        $this->occupy($cage, $day.' 11:30:00', $day.' 12:30:00');

        $res = $this->ask([
            'operator_id' => $operator->id,
            'resource_id' => $cage->id,
            'scheduled_at' => $day.' 11:00:00',
            'duration_minutes' => 60,
        ]);

        $res->assertOk();
        $res->assertJsonPath('resource.available', false);
        $this->assertNotEmpty($res->json('resource.busy'));
        $this->assertStringContainsString('11:30', $res->json('resource.busy.0.text'));
    }

    /**
     * `busy` (usado para `available` y el aviso "⚠ Jaula ocupada") solo cuenta lo que choca
     * con la ventana de estancia pedida — a propósito. Pero `day_busy` (usado para dibujar la
     * barra visual, a la misma escala que la del operador) debe traer TODO lo del día, aunque
     * la ventana pedida no toque esas reservas — si no, la barra casi nunca muestra nada real.
     */
    public function test_day_busy_reports_the_whole_day_even_when_it_does_not_overlap_the_requested_window(): void
    {
        $operator = Operator::create(['code' => 'OP8', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $cage = $this->cage('H1');
        $day = now()->addDay()->format('Y-m-d');
        $this->occupy($cage, $day.' 11:05:00', $day.' 12:00:00');
        $this->occupy($cage, $day.' 13:05:00', $day.' 15:05:00');

        // La ventana pedida (16:00–17:00) no toca ninguna de las dos reservas de la mañana.
        $res = $this->ask([
            'operator_id' => $operator->id,
            'resource_id' => $cage->id,
            'scheduled_at' => $day.' 16:00:00',
            'duration_minutes' => 60,
        ]);

        $res->assertOk();
        $res->assertJsonPath('resource.available', true);
        $res->assertJsonPath('resource.busy', []);
        $dayBusy = $res->json('resource.day_busy');
        $this->assertCount(2, $dayBusy);
        $this->assertSame('11:05', $dayBusy[0]['start']);
        $this->assertSame('12:00', $dayBusy[0]['end']);
        $this->assertSame('13:05', $dayBusy[1]['start']);
        $this->assertSame('15:05', $dayBusy[1]['end']);
    }

    public function test_day_busy_is_clipped_to_the_day_for_an_overnight_stay_in_progress(): void
    {
        $operator = Operator::create(['code' => 'OP9', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $cage = $this->cage('H2');
        $day = now()->addDay();
        // Un huésped que entró el día anterior y sale al mediodía de hoy.
        $this->occupy($cage, $day->copy()->subDay()->setTime(20, 0)->format('Y-m-d H:i:s'), $day->copy()->setTime(12, 0)->format('Y-m-d H:i:s'));

        $res = $this->ask([
            'operator_id' => $operator->id,
            'resource_id' => $cage->id,
            'scheduled_at' => $day->format('Y-m-d').' 15:00:00',
            'duration_minutes' => 60,
        ]);

        $res->assertOk();
        $dayBusy = $res->json('resource.day_busy');
        $this->assertCount(1, $dayBusy);
        // Recortado a [00:00, 12:00] de HOY, no al horario real de ayer 20:00.
        $this->assertSame('00:00', $dayBusy[0]['start']);
        $this->assertSame('12:00', $dayBusy[0]['end']);
    }

    public function test_check_availability_uses_the_custom_stay_window_for_the_clash(): void
    {
        $operator = Operator::create(['code' => 'OP4', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $cage = $this->cage('D1');
        $day = now()->addDay()->format('Y-m-d');
        $this->occupy($cage, $day.' 09:30:00', $day.' 10:00:00');

        // El servicio es 12:00–13:00 (libre), pero la estancia pedida 09:00–13:00 pisa 09:30.
        $res = $this->ask([
            'operator_id' => $operator->id,
            'resource_id' => $cage->id,
            'scheduled_at' => $day.' 12:00:00',
            'duration_minutes' => 60,
            'resource_starts_at' => $day.' 09:00',
            'resource_ends_at' => $day.' 13:00',
        ]);

        $res->assertOk();
        $res->assertJsonPath('resource.available', false);
        $this->assertStringEndsWith('09:00', $res->json('resource.window.start'));
        $this->assertStringEndsWith('13:00', $res->json('resource.window.end'));
    }

    public function test_stay_can_end_on_a_later_day_for_overnight_boarding(): void
    {
        $operator = Operator::create(['code' => 'OP7', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $cage = $this->cage('G2');
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'S3', 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 60, 'open_to_all_operators' => true]);
        $start = now()->addDay()->setTime(12, 0);

        $res = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'scheduled_at' => $start->format('Y-m-d H:i:s'),
            'services' => [$service->id],
            'service_operators' => [$service->id => $operator->id],
            'service_durations' => [$service->id => 60],
            'resource_id' => $cage->id,
            'resource_ends_at' => $start->copy()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'),
        ]);

        $res->assertRedirect(route('agenda.index'));
        $this->assertDatabaseHas('resource_allocations', [
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $start->copy()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_store_uses_the_custom_stay_window_around_the_service(): void
    {
        $operator = Operator::create(['code' => 'OP5', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $cage = $this->cage('E1');
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'S1', 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 60, 'open_to_all_operators' => true]);
        $day = now()->addDay()->format('Y-m-d');

        $res = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'scheduled_at' => $day.' 12:00:00',
            'services' => [$service->id],
            'service_operators' => [$service->id => $operator->id],
            'service_durations' => [$service->id => 60],
            'resource_id' => $cage->id,
            'resource_starts_at' => $day.' 10:00',   // el perro llega temprano
            'resource_ends_at' => $day.' 15:00',     // y lo recogen tarde
        ]);

        $res->assertRedirect(route('agenda.index'));
        $this->assertDatabaseHas('resource_allocations', [
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => $day.' 10:00:00',
            'ends_at' => $day.' 15:00:00',
        ]);
    }

    public function test_store_honors_a_stay_window_independent_of_the_service_window(): void
    {
        $operator = Operator::create(['code' => 'OP6', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $cage = $this->cage('F1');
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'S2', 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 60, 'open_to_all_operators' => true]);
        $day = now()->addDay()->format('Y-m-d');

        // El perro entra a la jaula al terminar el baño (13:00) y sale a las 18:00.
        $res = $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), [
            'scheduled_at' => $day.' 12:00:00',
            'services' => [$service->id],
            'service_operators' => [$service->id => $operator->id],
            'service_durations' => [$service->id => 60],
            'resource_id' => $cage->id,
            'resource_starts_at' => $day.' 13:00',
            'resource_ends_at' => $day.' 18:00',
        ]);

        $res->assertRedirect(route('agenda.index'));
        $this->assertDatabaseHas('resource_allocations', [
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => $day.' 13:00:00',
            'ends_at' => $day.' 18:00:00',
        ]);
    }

    public function test_a_repeated_identical_submit_does_not_double_book_the_cage(): void
    {
        // "Se crean tres reservas de la jaula que terminan empalmadas": un doble/triple clic en
        // "Guardar" (el board ARM tarda en responder) creaba 2–3 citas idénticas, cada una con
        // su par reserved + cleaning. Un POST idéntico repetido en < 20 s se descarta.
        $operator = Operator::create(['code' => 'OP7', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $cage = $this->cage('G1');
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'S3', 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 60, 'open_to_all_operators' => true]);
        $day = now()->addDay()->format('Y-m-d');

        $payload = [
            'scheduled_at' => $day.' 12:00:00',
            'services' => [$service->id],
            'service_operators' => [$service->id => $operator->id],
            'service_durations' => [$service->id => 60],
            'resource_id' => $cage->id,
            'resource_starts_at' => $day.' 13:00',
            'resource_ends_at' => $day.' 16:00',
        ];

        // 3 envíos idénticos seguidos. El 1º agenda; los siguientes o los frena el candado
        // (huella idéntica en < 20 s) o el chequeo de disponibilidad secuencial (el operador ya
        // quedó ocupado) — en ningún caso se duplica la cita ni la reserva de jaula.
        $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), $payload)->assertRedirect(route('agenda.index'));
        $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), $payload)->assertStatus(302);
        $this->actingAs($this->admin())->post(route('pets.bookings.store', $pet), $payload)->assertStatus(302);

        $this->assertSame(1, SpaBooking::where('pet_id', $pet->id)->count());
        $this->assertSame(1, ResourceAllocation::where('resource_id', $cage->id)->where('allocation_type', 'reserved')->count());
        $this->assertSame(1, ResourceAllocation::where('resource_id', $cage->id)->where('allocation_type', 'cleaning')->count());
    }
}
