<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;
    use CreatesAdminUser;

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
}
