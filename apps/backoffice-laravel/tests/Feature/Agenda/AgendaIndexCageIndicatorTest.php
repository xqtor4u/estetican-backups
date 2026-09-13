<?php

namespace Tests\Feature\Agenda;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-092: en la tabla de agenda del día (`AgUniInd`, `/agenda`) no había forma de ver, de un
 * vistazo, si una cita tiene jaula asignada — para eso hacía falta abrir el detalle. Se agrega
 * un indicador compacto junto al nombre de la mascota.
 */
class AgendaIndexCageIndicatorTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function bookingWithService(string $petName = 'Luka'): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => $petName]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño y corte', 'type' => 'grooming', 'price' => 350, 'duration_minutes' => 60, 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'total_estimated_price' => 350,
        ]);

        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => 350]);

        return $booking;
    }

    private function cage(string $code = 'JG-001'): Resource
    {
        $branch = Branch::create(['code' => 'MTY-'.$code, 'name' => 'Sucursal', 'is_active' => true]);

        return Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => $code,
            'name' => 'Jaula Grande',
            'administrative_status' => 'active',
            'operational_status' => 'available',
        ]);
    }

    public function test_day_table_shows_the_assigned_cage_next_to_the_pet(): void
    {
        $booking = $this->bookingWithService();
        $cage = $this->cage();

        ResourceAllocation::create([
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'source_type' => $booking->getMorphClass(),
            'source_id' => $booking->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $response->assertOk();
        $response->assertSee('JG-001', false);
        $response->assertSee('🏠', false);
    }

    public function test_day_table_shows_nothing_extra_when_no_cage_is_assigned(): void
    {
        $this->bookingWithService();

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $response->assertOk();
        $response->assertDontSee('🏠', false);
    }

    public function test_day_table_ignores_the_cleaning_buffer_allocation(): void
    {
        // La reserva real ('reserved') es lo que se muestra; el bloqueo de limpieza posterior
        // ('cleaning') es un detalle interno de `ResourceAllocationService`, no algo que el
        // piso necesite ver junto al nombre de la mascota.
        $booking = $this->bookingWithService();
        $cage = $this->cage('JG-002');

        ResourceAllocation::create([
            'resource_id' => $cage->id,
            'allocation_type' => 'cleaning',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHour()->addMinutes(15),
            'source_type' => $booking->getMorphClass(),
            'source_id' => $booking->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $response->assertOk();
        $response->assertDontSee('JG-002', false);
    }
}
