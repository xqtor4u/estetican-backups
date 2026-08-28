<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * BL-075 vía API móvil: asignar profesional / costo externo por línea de servicio,
 * y verificar que sincronizar la lista de servicios de una cita (PATCH /api/bookings/{id})
 * ya no borra y recrea todo (lo que perdía operador/costo externo/precio editado —
 * ver el fix en BookingController::update()).
 */
class BookingServiceAssignmentTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function apiHeaders(): array
    {
        $user = $this->createAdminUser(['email' => 'admin-svc-assign-test-'.uniqid().'@example.com']);
        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['Authorization' => "Bearer {$plainToken}"];
    }

    private function bookingWithServiceLine(float $price = 1000): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Cirugía', 'type' => 'extra', 'price' => $price, 'duration_minutes' => 60, 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => $price,
        ]);

        $line = SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'current_price' => $price,
        ]);

        return [$booking, $line, $service];
    }

    public function test_assigns_operator_and_external_cost_to_a_service_line_via_api(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine(price: 1000);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Vet Externo', 'first_name' => 'Vet', 'is_active' => true]);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", [
            'operator_id' => $operator->id,
            'is_external' => true,
            'external_cost' => 600,
        ]);

        $response->assertOk();
        $response->assertJsonPath('services.0.operator_id', $operator->id);
        $response->assertJsonPath('services.0.is_external', true);
        $response->assertJsonPath('services.0.external_cost', 600);
        $response->assertJsonPath('services.0.price', 1000); // el precio de venta no se mueve solo

        $line->refresh();
        $this->assertSame($operator->id, $line->operator_id);
        $this->assertEquals(600.0, (float) $line->external_cost);
    }

    public function test_rejects_assignment_to_a_line_from_another_booking(): void
    {
        [, $lineA] = $this->bookingWithServiceLine();
        [$bookingB] = $this->bookingWithServiceLine();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$bookingB->id}/services/{$lineA->id}", [
            'operator_id' => $operator->id,
        ]);

        $response->assertNotFound();
    }

    public function test_syncing_services_on_the_booking_preserves_operator_and_external_cost_of_untouched_lines(): void
    {
        [$booking, $lineA, $serviceA] = $this->bookingWithServiceLine(price: 1000);
        $serviceB = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true]);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Vet Externo', 'first_name' => 'Vet', 'is_active' => true]);

        // Se asigna operador/costo externo/precio editado a la línea A
        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$lineA->id}", [
            'operator_id' => $operator->id,
            'is_external' => true,
            'external_cost' => 600,
            'current_price' => 1200,
        ]);

        // El staff, desde mobile, agrega el servicio B a la misma cita (reenvía la lista completa de IDs)
        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}", [
            'services' => [$serviceA->id, $serviceB->id],
        ]);

        $response->assertOk();

        $lineA->refresh();
        $this->assertSame($operator->id, $lineA->operator_id, 'la línea A no debió perder el operador asignado');
        $this->assertTrue((bool) $lineA->is_external);
        $this->assertEquals(600.0, (float) $lineA->external_cost);
        $this->assertEquals(1200.0, (float) $lineA->current_price, 'la línea A no debió perder el precio editado');

        $booking->refresh();
        $this->assertCount(2, $booking->services);
        $lineB = $booking->services->firstWhere('service_id', $serviceB->id);
        $this->assertEquals(300.0, (float) $lineB->current_price); // línea nueva, precio de catálogo
        $this->assertEquals(1500.0, (float) $booking->total_estimated_price); // 1200 + 300
    }

    public function test_syncing_services_removes_lines_no_longer_selected(): void
    {
        [$booking, $lineA, $serviceA] = $this->bookingWithServiceLine();
        $serviceB = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true]);
        $booking->services()->create(['service_id' => $serviceB->id, 'current_price' => 300]);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}", [
            'services' => [$serviceA->id],
        ]);

        $response->assertOk();
        $booking->refresh();
        $this->assertCount(1, $booking->services);
        $this->assertSame($serviceA->id, $booking->services->first()->service_id);
    }

    public function test_can_edit_a_line_price_alone_without_reassigning_an_operator(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine(price: 500);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", [
            'current_price' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('services.0.price', 0);
        $response->assertJsonPath('total', 0);
        $line->refresh();
        $this->assertNull($line->operator_id, 'no debió forzar/tocar el operador');
        $this->assertEquals(0.0, (float) $line->current_price);

        $booking->refresh();
        $this->assertEquals(0.0, (float) $booking->total_estimated_price);
    }

    public function test_editing_one_lines_price_recomputes_the_booking_total_from_all_lines(): void
    {
        [$booking, $lineA, $serviceA] = $this->bookingWithServiceLine(price: 500);
        $serviceB = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true]);
        $booking->services()->create(['service_id' => $serviceB->id, 'current_price' => 300]);
        $booking->update(['total_estimated_price' => 800]);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$lineA->id}", [
            'current_price' => 0,
        ]);

        $response->assertOk();
        $booking->refresh();
        $this->assertEquals(300.0, (float) $booking->total_estimated_price); // 0 (regalado) + 300
    }

    public function test_mark_started_on_a_line_of_a_scheduled_booking_promotes_it_to_work_order(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $bath = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30]);
        $cut = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Corte', 'type' => 'spa', 'price' => 200, 'duration_minutes' => 20]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'total_estimated_price' => 500,
        ]);
        $bathLine = $booking->services()->create(['service_id' => $bath->id, 'current_price' => 300]);
        $cutLine = $booking->services()->create(['service_id' => $cut->id, 'current_price' => 200]);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$bathLine->id}", [
            'mark_started' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'work_order');

        $booking->refresh();
        $this->assertSame('work_order', $booking->status);

        $bathLine->refresh();
        $cutLine->refresh();
        $this->assertNotNull($bathLine->started_at, 'la línea marcada debió quedar iniciada');
        $this->assertNull($cutLine->started_at, 'la línea no marcada debe seguir pendiente, no arrancar sola');
    }

    public function test_mark_started_is_idempotent_and_does_not_move_an_already_started_line(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine();

        Carbon::setTestNow('2026-08-21 10:00:00');
        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", ['mark_started' => true]);
        $line->refresh();
        $firstStartedAt = $line->started_at;

        Carbon::setTestNow('2026-08-21 10:30:00');
        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", ['mark_started' => true]);
        $line->refresh();

        Carbon::setTestNow();
        $this->assertTrue($firstStartedAt->equalTo($line->started_at), 'reintentar mark_started no debió mover la hora de inicio real');
    }

    public function test_patch_without_mark_started_never_touches_started_at(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine();

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", [
            'current_price' => 42,
        ]);

        $response->assertOk();
        $line->refresh();
        $this->assertNull($line->started_at);
    }

    public function test_rejects_mark_completed_on_a_line_that_never_started(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine();

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", [
            'mark_completed' => true,
        ]);

        $response->assertStatus(422);
        $line->refresh();
        $this->assertNull($line->completed_at);
    }

    public function test_mark_completed_on_a_started_line_sets_completed_at(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine();
        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", ['mark_started' => true]);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", [
            'mark_completed' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('services.0.completed_at', fn ($v) => $v !== null);
        $line->refresh();
        $this->assertNotNull($line->completed_at);
        // Completar una línea es independiente de cobrar/cerrar la cita — no debe tocar el status.
        $booking->refresh();
        $this->assertSame('work_order', $booking->status);
    }

    /* ── No se realizó / Cancelar por línea (SYNC-044) ─────────────────────── */

    /** @return array{0: SpaBooking, 1: SpaBookingService, 2: SpaBookingService} */
    private function bookingWithTwoLines(float $a = 300, float $b = 200): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $svcA = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => $a, 'duration_minutes' => 30]);
        $svcB = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Corte', 'type' => 'spa', 'price' => $b, 'duration_minutes' => 20]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => $a + $b,
        ]);
        $lineA = $booking->services()->create(['service_id' => $svcA->id, 'current_price' => $a]);
        $lineB = $booking->services()->create(['service_id' => $svcB->id, 'current_price' => $b]);

        return [$booking, $lineA, $lineB];
    }

    public function test_mark_not_performed_excludes_the_line_from_the_booking_total(): void
    {
        [$booking, $lineA, $lineB] = $this->bookingWithTwoLines(a: 300, b: 200);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$lineB->id}", [
            'mark_not_performed' => true,
            'not_performed_reason' => 'La mascota no cooperó',
        ]);

        $response->assertOk();
        $lineB->refresh();
        $this->assertNotNull($lineB->not_performed_at);
        $this->assertSame('La mascota no cooperó', $lineB->not_performed_reason);

        $booking->refresh();
        $this->assertEquals(300.0, (float) $booking->total_estimated_price);
    }

    public function test_mark_cancelled_excludes_the_line_from_the_booking_total(): void
    {
        [$booking, $lineA, $lineB] = $this->bookingWithTwoLines(a: 300, b: 200);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$lineA->id}", [
            'mark_cancelled' => true,
            'cancellation_reason' => 'El cliente ya no lo quiere',
        ]);

        $response->assertOk();
        $lineA->refresh();
        $this->assertNotNull($lineA->cancelled_at);

        $booking->refresh();
        $this->assertEquals(200.0, (float) $booking->total_estimated_price);
    }

    public function test_mark_reactivate_restores_the_line_and_the_total(): void
    {
        [$booking, $lineA, $lineB] = $this->bookingWithTwoLines(a: 300, b: 200);

        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$lineB->id}", [
            'mark_not_performed' => true,
        ])->assertOk();

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$lineB->id}", [
            'mark_reactivate' => true,
        ]);

        $response->assertOk();
        $lineB->refresh();
        $this->assertNull($lineB->not_performed_at);
        $this->assertNull($lineB->cancelled_at);

        $booking->refresh();
        $this->assertEquals(500.0, (float) $booking->total_estimated_price);
    }

    public function test_cannot_start_or_complete_a_voided_line(): void
    {
        [$booking, $lineA] = $this->bookingWithTwoLines();

        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$lineA->id}", [
            'mark_cancelled' => true,
        ])->assertOk();

        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$lineA->id}", [
            'mark_started' => true,
        ])->assertStatus(422);

        $lineA->refresh();
        $this->assertNull($lineA->started_at);
    }

    public function test_mark_realizada_completes_a_pending_line_backfilling_started_at(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine();

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", [
            'mark_realizada' => true,
        ]);

        $response->assertOk();
        $line->refresh();
        $this->assertNotNull($line->started_at, 'realizada rellena started_at aunque no se haya tocado Iniciar');
        $this->assertNotNull($line->completed_at);
    }

    public function test_cannot_void_an_already_completed_line(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine();
        $line->update(['started_at' => now(), 'completed_at' => now()]);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", [
            'mark_not_performed' => true,
        ]);

        $response->assertStatus(422);
        $line->refresh();
        $this->assertNull($line->not_performed_at);
    }

    public function test_mark_completed_is_idempotent_and_does_not_move_an_already_completed_line(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine();
        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", ['mark_started' => true]);

        Carbon::setTestNow('2026-08-21 11:00:00');
        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", ['mark_completed' => true]);
        $line->refresh();
        $firstCompletedAt = $line->completed_at;

        Carbon::setTestNow('2026-08-21 11:30:00');
        $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}/services/{$line->id}", ['mark_completed' => true]);
        $line->refresh();

        Carbon::setTestNow();
        $this->assertTrue($firstCompletedAt->equalTo($line->completed_at));
    }
}
