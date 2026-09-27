<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\CreatesRestrictedOperatorUser;
use Tests\TestCase;

/**
 * ZEUS-043 — GET /api/resources y GET /api/resources/{resource}/availability: piezas de
 * backend nuevas para el popup de horario landscape de `tstmov` (SPEC_POPUP_HORARIO_LANDSCAPE_TSTMOV.md
 * §3.2). `availability()` delega en `ResourceAllocationService::availabilitySummary()`, movido
 * ahí desde `SpaBookingController` (§3.1) — su lógica interna ya está cubierta por
 * `AgendaResourceAvailabilityTest`/`CheckAvailabilityTest`; estos tests solo cubren el cableado
 * nuevo (filtro de listado, permisos).
 */
class ResourceEndpointsTest extends TestCase
{
    use CreatesAdminUser;
    use CreatesRestrictedOperatorUser;
    use RefreshDatabase;

    private function cage(string $code, string $administrativeStatus = 'active'): Resource
    {
        $branch = Branch::create(['code' => 'MTY-'.$code, 'name' => 'Sucursal '.$code, 'is_active' => true]);

        return Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => $code,
            'name' => 'Jaula '.$code,
            'administrative_status' => $administrativeStatus,
            'operational_status' => 'available',
        ]);
    }

    public function test_index_lists_active_and_inactive_cages_but_not_retired(): void
    {
        $active = $this->cage('A1', 'active');
        $inactive = $this->cage('A2', 'inactive');
        $retired = $this->cage('A3', 'retired');

        $response = $this->withHeaders($this->createAdminAuthHeader())->getJson('/api/resources');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$active->id, $inactive->id], $ids);
        $this->assertNotContains($retired->id, $ids);
    }

    public function test_index_defaults_to_cage_type_only(): void
    {
        $cage = $this->cage('A1');
        $branch = Branch::create(['code' => 'MTY-R1', 'name' => 'Sucursal R1', 'is_active' => true]);
        $room = Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'room',
            'code' => 'R1',
            'name' => 'Sala 1',
            'administrative_status' => 'active',
            'operational_status' => 'available',
        ]);

        $response = $this->withHeaders($this->createAdminAuthHeader())->getJson('/api/resources');

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertSame([$cage->id], $ids);
        $this->assertNotContains($room->id, $ids);
    }

    public function test_index_requires_ver_agenda_permission(): void
    {
        $this->cage('A1');
        $user = $this->createOperatorUser();

        $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson('/api/resources')
            ->assertForbidden();
    }

    public function test_availability_reports_busy_window_from_an_existing_allocation(): void
    {
        $cage = $this->cage('A1');
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Otro']);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'status' => 'scheduled',
            'duration_minutes' => 60,
            'total_estimated_price' => 100,
        ]);
        $day = now()->addDay()->format('Y-m-d');
        ResourceAllocation::create([
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => "{$day} 10:00:00",
            'ends_at' => "{$day} 11:00:00",
            'source_type' => $booking->getMorphClass(),
            'source_id' => $booking->id,
        ]);

        $response = $this->withHeaders($this->createAdminAuthHeader())
            ->getJson("/api/resources/{$cage->id}/availability?".http_build_query([
                'date' => $day,
                'resource_starts_at' => "{$day} 10:00:00",
                'resource_ends_at' => "{$day} 11:00:00",
            ]));

        $response->assertOk();
        $response->assertJson(['available' => false]);
        $this->assertCount(1, $response->json('day_busy'));
        $this->assertSame('10:00', $response->json('day_busy.0.start'));
        $this->assertSame('11:00', $response->json('day_busy.0.end'));
    }

    public function test_availability_reports_available_when_window_is_free(): void
    {
        $cage = $this->cage('A1');
        $day = now()->addDay()->format('Y-m-d');

        $response = $this->withHeaders($this->createAdminAuthHeader())
            ->getJson("/api/resources/{$cage->id}/availability?".http_build_query([
                'date' => $day,
                'resource_starts_at' => "{$day} 10:00:00",
                'resource_ends_at' => "{$day} 11:00:00",
            ]));

        $response->assertOk();
        $response->assertJson(['available' => true]);
        $this->assertCount(0, $response->json('day_busy'));
    }

    public function test_availability_requires_ver_agenda_permission(): void
    {
        $cage = $this->cage('A1');
        $user = $this->createOperatorUser();

        $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson("/api/resources/{$cage->id}/availability?date=".now()->addDay()->format('Y-m-d'))
            ->assertForbidden();
    }
}
