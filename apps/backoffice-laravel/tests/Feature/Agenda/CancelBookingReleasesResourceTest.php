<?php

namespace Tests\Feature\Agenda;

use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-107 — cancelar una cita (SpaBooking) no liberaba la jaula/recurso asignado al
 * agendar: `BookingService::cancelBooking()` solo tocaba `status`/`cancellation_reason`,
 * a diferencia de `HotelReservationController::cancel()` (mismo `ResourceAllocationService`),
 * que sí llama `releaseSourceAllocations()` — ver
 * `HotelReservationResourceBlockingTest::test_cancelling_hotel_reservation_releases_the_cage_block`.
 */
class CancelBookingReleasesResourceTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    public function test_cancelling_a_spa_booking_releases_its_assigned_cage(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $branch = Branch::create(['code' => 'MTY-CEN'.uniqid(), 'name' => 'Monterrey Centro', 'is_active' => true]);
        $resource = Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => 'J-'.uniqid(),
            'name' => 'Jaula de prueba',
            'capacity_label' => 'Mediana',
            'administrative_status' => 'active',
            'operational_status' => 'available',
        ]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
            'duration_minutes' => 60,
            'total_estimated_price' => 300,
        ]);

        app(ResourceAllocationServiceInterface::class)->assignResourceToSource(
            $resource->id,
            $booking,
            $pet->id,
            $booking->scheduled_at->format('Y-m-d H:i:s'),
            60,
        );

        $this->assertGreaterThan(0, $booking->resourceAllocations()->count(), 'la jaula debió quedar asignada antes de cancelar');

        $response = $this->actingAs($this->admin())->post(route('agenda.cancel', $booking), [
            'cancellation_reason' => 'El cliente ya no puede venir',
        ]);

        $response->assertRedirect(route('agenda.index'));
        $this->assertDatabaseHas('spa_bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseMissing('resource_allocations', [
            'source_type' => $booking->getMorphClass(),
            'source_id' => $booking->id,
        ]);
    }
}
