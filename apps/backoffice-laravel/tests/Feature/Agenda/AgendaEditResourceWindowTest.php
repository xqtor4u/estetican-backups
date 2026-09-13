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
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Tomas (SYNC-093): AgSpaEdi solo dejaba asignar una jaula con la estancia forzada a
 * `scheduled_at` + duración del servicio — a diferencia de AgSpaCre, que desde `SYNC-086` deja
 * Entra/Sale independientes (el perro puede quedarse después de terminado el servicio).
 * `SpaBookingController::update()` gana el mismo comportamiento.
 *
 * De paso se encontró y corrigió un bug real: `update()` leía
 * `backoffice.resources.cleaning_buffer_minutes` — clave que no existe en
 * `config/backoffice.php` — así que SIEMPRE aplicaba 30 min de limpieza fijos sin importar lo
 * configurado, distinto del buffer real (`backoffice.system.resource_cleaning_buffer_minutes`,
 * 15 min por defecto) que ya usan `storeForPet`/`resourceAvailabilitySummary`.
 */
class AgendaEditResourceWindowTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
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

    private function bookingWithService(): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño y corte', 'type' => 'grooming', 'price' => 350, 'duration_minutes' => 60, 'is_active' => true, 'open_to_all_operators' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
            'total_estimated_price' => 350,
        ]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => 350]);

        return $booking;
    }

    public function test_update_with_an_explicit_stay_window_uses_it_instead_of_the_service_window(): void
    {
        $booking = $this->bookingWithService();
        $cage = $this->cage();
        $scheduledAt = $booking->scheduled_at;
        // El perro entra al terminar el servicio y se queda 3 horas — bien distinto de
        // "scheduled_at + duración del servicio" (1 hora) que era lo único posible antes.
        $stayStart = $scheduledAt->copy()->addHour();
        $stayEnd = $stayStart->copy()->addHours(3);

        $response = $this->actingAs($this->admin())->put(route('agenda.update', $booking), [
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'operator_id' => $booking->operator_id,
            'resource_id' => $cage->id,
            'resource_starts_at' => $stayStart->format('Y-m-d H:i:s'),
            'resource_ends_at' => $stayEnd->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('agenda.show', $booking));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('warning');

        $allocation = ResourceAllocation::where('resource_id', $cage->id)
            ->where('allocation_type', 'reserved')->firstOrFail();
        $this->assertTrue($allocation->starts_at->equalTo($stayStart));
        $this->assertTrue($allocation->ends_at->equalTo($stayEnd));
    }

    public function test_update_without_an_explicit_stay_window_falls_back_to_the_service_window(): void
    {
        $booking = $this->bookingWithService();
        $cage = $this->cage();
        $scheduledAt = $booking->scheduled_at;

        $this->actingAs($this->admin())->put(route('agenda.update', $booking), [
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'operator_id' => $booking->operator_id,
            'resource_id' => $cage->id,
        ]);

        $allocation = ResourceAllocation::where('resource_id', $cage->id)
            ->where('allocation_type', 'reserved')->firstOrFail();
        $this->assertTrue($allocation->starts_at->equalTo($scheduledAt));
    }

    public function test_cleaning_buffer_uses_the_configured_key_not_the_old_hardcoded_thirty(): void
    {
        // Un valor que no es ni el default (15) ni el viejo hardcode roto (30) — si `update()`
        // volviera a leer la clave equivocada (`backoffice.resources.*`, que no existe), esta
        // aserción fallaría porque caería en 30 en vez del 22 configurado aquí.
        config(['backoffice.system.resource_cleaning_buffer_minutes' => 22]);
        $booking = $this->bookingWithService();
        $cage = $this->cage();
        $scheduledAt = $booking->scheduled_at;

        $this->actingAs($this->admin())->put(route('agenda.update', $booking), [
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'operator_id' => $booking->operator_id,
            'resource_id' => $cage->id,
        ]);

        $cleaning = ResourceAllocation::where('resource_id', $cage->id)
            ->where('allocation_type', 'cleaning')->firstOrFail();
        $this->assertEqualsWithDelta(22, $cleaning->starts_at->diffInMinutes($cleaning->ends_at), 0.01);
    }

    public function test_a_cage_conflict_on_update_warns_but_still_saves_the_rest_of_the_change(): void
    {
        $booking = $this->bookingWithService();
        $cage = $this->cage();
        $scheduledAt = $booking->scheduled_at;

        // Otra cita ya tiene la jaula ocupada exactamente en la ventana que se va a pedir.
        $otherClient = Client::create(['first_name' => 'Beto', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $otherPet = Pet::create(['client_id' => $otherClient->id, 'name' => 'Otro']);
        $otherBooking = SpaBooking::create([
            'pet_id' => $otherPet->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'total_estimated_price' => 100,
        ]);
        ResourceAllocation::create([
            'resource_id' => $cage->id,
            'allocation_type' => 'reserved',
            'starts_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addHours(4),
            'source_type' => $otherBooking->getMorphClass(),
            'source_id' => $otherBooking->id,
        ]);

        $response = $this->actingAs($this->admin())->put(route('agenda.update', $booking), [
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'operator_id' => $booking->operator_id,
            'resource_id' => $cage->id,
            'notes' => 'Ajuste de notas de piso',
        ]);

        $response->assertRedirect(route('agenda.show', $booking));
        $response->assertSessionHas('success');
        $response->assertSessionHas('warning');

        // La cita sí se actualizó (notas), la jaula se quedó sin asignar para esta cita.
        $booking->refresh();
        $this->assertSame('Ajuste de notas de piso', $booking->notes);
        $this->assertSame(0, ResourceAllocation::where('resource_id', $cage->id)
            ->where('source_type', $booking->getMorphClass())
            ->where('source_id', $booking->id)
            ->count());
    }

    /**
     * SYNC-095 (9ª vuelta): Tomas, tras confirmar la jaula: "la cita se puede mover pero no
     * redimensionar" — pidió poder alargar/acortar la cita completa arrastrando su borde en
     * `AgSpaEdi`. Antes, `update()` siempre recalculaba `duration_minutes` desde cero (suma de
     * catálogo de los servicios asignados), así que no había forma de que una duración distinta
     * sobreviviera a una edición — ni de guardarla, ni de leerla de vuelta al reabrir. Ahora
     * `duration_minutes` es un campo aceptado por `update()` (viaja desde el hidden que el
     * arrastre del borde actualiza) y se persiste en la cita.
     */
    public function test_update_with_an_explicit_duration_persists_it_instead_of_the_catalog_sum(): void
    {
        $booking = $this->bookingWithService(); // servicio de catálogo: 60 min
        $scheduledAt = $booking->scheduled_at;

        $response = $this->actingAs($this->admin())->put(route('agenda.update', $booking), [
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'operator_id' => $booking->operator_id,
            'duration_minutes' => 90, // arrastrada 30 min más allá del catálogo
        ]);

        $response->assertRedirect(route('agenda.show', $booking));
        $booking->refresh();
        $this->assertSame(90, $booking->duration_minutes);
    }

    public function test_update_without_an_explicit_duration_falls_back_to_the_catalog_sum(): void
    {
        $booking = $this->bookingWithService(); // servicio de catálogo: 60 min
        $scheduledAt = $booking->scheduled_at;

        $this->actingAs($this->admin())->put(route('agenda.update', $booking), [
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'operator_id' => $booking->operator_id,
        ]);

        $booking->refresh();
        $this->assertSame(60, $booking->duration_minutes);
    }

    public function test_edit_page_reads_back_a_previously_persisted_duration_not_the_catalog_sum(): void
    {
        $booking = $this->bookingWithService(); // servicio de catálogo: 60 min
        $booking->forceFill(['duration_minutes' => 105])->save();

        $res = $this->actingAs($this->admin())->get(route('agenda.edit', $booking));

        $res->assertOk();
        $res->assertSee('var currentDurationMinutes = 105', false);
        $res->assertSee('value="105"', false); // el hidden #duration_minutes_input
    }

    public function test_extending_the_duration_that_would_overlap_another_operator_booking_is_rejected(): void
    {
        $booking = $this->bookingWithService(); // 11:00, catálogo 60 min
        $scheduledAt = $booking->scheduled_at;

        // Otra cita real del MISMO operador empieza justo donde terminaría la cita extendida
        // (11:00 + 90 min = 12:30), pero cabe perfecto con la duración original (60 min → 12:00).
        $otherClient = Client::create(['first_name' => 'Beto', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $otherPet = Pet::create(['client_id' => $otherClient->id, 'name' => 'Otro']);
        SpaBooking::create([
            'pet_id' => $otherPet->id,
            'operator_id' => $booking->operator_id,
            'scheduled_at' => $scheduledAt->copy()->addMinutes(75),
            'status' => 'scheduled',
            'duration_minutes' => 30,
            'total_estimated_price' => 100,
        ]);

        $response = $this->actingAs($this->admin())->put(route('agenda.update', $booking), [
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'operator_id' => $booking->operator_id,
            'duration_minutes' => 90, // 11:00-12:30 — choca con la otra cita (12:15-12:45)
        ]);

        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertNull($booking->duration_minutes); // no se guardó nada
    }
}
