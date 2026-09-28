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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** Cita de mañana con jaula asignada — misma preparación que el caso de cancelación. */
    private function bookingWithCage(): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $branch = Branch::create(['code' => 'MTY-'.uniqid(), 'name' => 'Monterrey', 'is_active' => true]);
        $resource = Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => 'J-'.uniqid(),
            'name' => 'Jaula',
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
            $resource->id, $booking, $pet->id, $booking->scheduled_at->format('Y-m-d H:i:s'), 60,
        );
        $this->assertGreaterThan(0, $booking->resourceAllocations()->count());

        return $booking;
    }

    private function assertCageReleased(SpaBooking $booking, string $status): void
    {
        $this->assertSame($status, $booking->fresh()->status);
        $this->assertDatabaseMissing('resource_allocations', [
            'source_type' => $booking->getMorphClass(),
            'source_id' => $booking->id,
        ]);
    }

    public function test_marking_no_show_on_the_web_releases_the_cage(): void
    {
        $booking = $this->bookingWithCage();

        $this->actingAs($this->admin())->post(route('agenda.no-show', $booking), ['reason' => 'No llegó']);

        $this->assertCageReleased($booking, 'no_show');
    }

    public function test_marking_unfulfillable_on_the_web_releases_the_cage(): void
    {
        $booking = $this->bookingWithCage();

        $this->actingAs($this->admin())->post(route('agenda.unfulfillable', $booking), ['reason' => 'No cooperó']);

        $this->assertCageReleased($booking, 'unfulfillable');
    }

    /** @return array<string, array{string}> */
    public static function notPerformedStatuses(): array
    {
        return ['cancelada' => ['cancelled'], 'no se presentó' => ['no_show'], 'no realizable' => ['unfulfillable']];
    }

    #[DataProvider('notPerformedStatuses')]
    public function test_mobile_status_change_to_a_not_performed_status_releases_the_cage(string $status): void
    {
        $booking = $this->bookingWithCage();

        $this->withHeaders($this->createAdminAuthHeader())
            ->patchJson('/api/bookings/'.$booking->id, ['status' => $status, 'cancellation_reason' => 'Prueba'])
            ->assertOk();

        $this->assertCageReleased($booking, $status);
    }
}
