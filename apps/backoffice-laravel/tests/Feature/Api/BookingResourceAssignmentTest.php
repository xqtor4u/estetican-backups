<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * ZEUS-043 — `Api\BookingController::store`/`update` aceptan jaula (`resource_id` +
 * ventana propia opcional), spec §3.3. Antes de esto la API móvil no tenía ningún manejo de
 * `resource_id` — toda la asignación de jaula era exclusiva del flujo web
 * (`SpaBookingController`). Mismo comportamiento de "aviso suave, no bloqueo duro" ante un
 * choque que ya usa el web desde SYNC-093.
 */
class BookingResourceAssignmentTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function authHeader(): array
    {
        return $this->createAdminAuthHeader();
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function operator(): Operator
    {
        return Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
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

    public function test_store_without_resource_id_creates_no_allocation(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
        ]);

        $response->assertCreated();
        $response->assertJson(['resource' => null, 'resource_warning' => null]);
        $this->assertSame(0, ResourceAllocation::count());
    }

    public function test_store_with_resource_id_assigns_the_cage_using_the_service_window_by_default(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();
        $cage = $this->cage();
        $scheduledAt = now()->addDay()->setTime(11, 0);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'resource_id' => $cage->id,
        ]);

        $response->assertCreated();
        $response->assertJson(['resource_warning' => null]);
        $this->assertSame($cage->id, $response->json('resource.id'));
        $this->assertSame($scheduledAt->format('Y-m-d H:i:s'), $response->json('resource.starts_at'));
        $this->assertSame($scheduledAt->copy()->addMinutes(60)->format('Y-m-d H:i:s'), $response->json('resource.ends_at'));

        $booking = SpaBooking::findOrFail($response->json('id'));
        $allocation = ResourceAllocation::where('source_type', $booking->getMorphClass())
            ->where('source_id', $booking->id)
            ->where('allocation_type', 'reserved')
            ->first();
        $this->assertNotNull($allocation);
        $this->assertSame($cage->id, $allocation->resource_id);
    }

    public function test_store_with_a_conflicting_cage_still_creates_the_booking_and_warns(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();
        $cage = $this->cage();
        $scheduledAt = now()->addDay()->setTime(11, 0);

        // Ocupa la jaula de antemano con otra cita.
        $otherPet = $this->pet();
        $otherBooking = SpaBooking::create([
            'pet_id' => $otherPet->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'duration_minutes' => 60,
            'total_estimated_price' => 100,
        ]);
        ResourceAllocation::create([
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'ends_at' => $scheduledAt->copy()->addMinutes(60)->format('Y-m-d H:i:s'),
            'source_type' => $otherBooking->getMorphClass(),
            'source_id' => $otherBooking->id,
        ]);

        $response = $this->withHeaders($this->authHeader())->postJson('/api/bookings', [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'resource_id' => $cage->id,
        ]);

        // La cita sí se crea (201) — el choque de jaula es un aviso suave, no un bloqueo duro
        // (spec §7, mismo criterio que `SpaBookingController::storeForPet`).
        $response->assertCreated();
        $this->assertNotNull($response->json('resource_warning'));
        $this->assertNull($response->json('resource'));

        // Solo existe la asignación previa — la nueva cita no se quedó con la jaula.
        $this->assertSame(1, ResourceAllocation::where('resource_id', $cage->id)->count());
    }

    public function test_update_assigns_a_cage_to_an_already_scheduled_booking(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();
        $cage = $this->cage();
        $scheduledAt = now()->addDay()->setTime(11, 0);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'duration_minutes' => 45,
            'total_estimated_price' => 100,
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->patchJson("/api/bookings/{$booking->id}", ['resource_id' => $cage->id]);

        $response->assertOk();
        $response->assertJson(['resource_warning' => null]);
        $this->assertSame($cage->id, $response->json('resource.id'));
        // Sin ventana propia, cae a la del servicio (scheduled_at + duration_minutes vigentes).
        $this->assertSame($scheduledAt->format('Y-m-d H:i:s'), $response->json('resource.starts_at'));
        $this->assertSame($scheduledAt->copy()->addMinutes(45)->format('Y-m-d H:i:s'), $response->json('resource.ends_at'));
    }

    public function test_update_with_resource_id_null_releases_the_cage(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();
        $cage = $this->cage();
        $scheduledAt = now()->addDay()->setTime(11, 0);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'duration_minutes' => 45,
            'total_estimated_price' => 100,
        ]);
        ResourceAllocation::create([
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'ends_at' => $scheduledAt->copy()->addMinutes(45)->format('Y-m-d H:i:s'),
            'source_type' => $booking->getMorphClass(),
            'source_id' => $booking->id,
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->patchJson("/api/bookings/{$booking->id}", ['resource_id' => null]);

        $response->assertOk();
        $this->assertNull($response->json('resource'));
        $this->assertSame(0, ResourceAllocation::where('source_id', $booking->id)->count());
    }

    public function test_update_can_set_an_independent_resource_window(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();
        $cage = $this->cage();
        $scheduledAt = now()->addDay()->setTime(11, 0);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'duration_minutes' => 45,
            'total_estimated_price' => 100,
        ]);

        // La estancia arranca antes y termina después del servicio — independiente por
        // diseño (spec §0.9: el backend ya lo admite desde el día 1, aunque la v1 de tstmov
        // siempre mande la misma ventana que el servicio).
        $stayStart = $scheduledAt->copy()->subMinutes(15);
        $stayEnd = $scheduledAt->copy()->addMinutes(45 + 30);

        $response = $this->withHeaders($this->authHeader())->patchJson("/api/bookings/{$booking->id}", [
            'resource_id' => $cage->id,
            'resource_starts_at' => $stayStart->format('Y-m-d H:i:s'),
            'resource_ends_at' => $stayEnd->format('Y-m-d H:i:s'),
        ]);

        $response->assertOk();
        $this->assertSame($stayStart->format('Y-m-d H:i:s'), $response->json('resource.starts_at'));
        $this->assertSame($stayEnd->format('Y-m-d H:i:s'), $response->json('resource.ends_at'));
    }
}
