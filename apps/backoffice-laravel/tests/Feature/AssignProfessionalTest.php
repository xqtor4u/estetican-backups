<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Operator;
use App\Models\OperatorRole;
use App\Models\OperatorRoleServiceTemplate;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class AssignProfessionalTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function bookingWithServiceLine(float $price = 1000): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Cirugía', 'type' => 'extra', 'price' => $price, 'duration_minutes' => 60, 'is_active' => true, 'open_to_all_operators' => true]);

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

        return [$booking, $line];
    }

    /** Servicio que exige un rol específico, vía plantilla (SYNC-073) — mismo patrón que
     *  Api\BookingSchedulingValidationTest::serviceRequiringRole(). */
    private function bookingWithRoleRestrictedLine(OperatorRole $role, float $price = 1000): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Consulta', 'type' => 'spa', 'price' => $price, 'duration_minutes' => 30, 'is_active' => true]);
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $service->id]);

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

    public function test_rejects_assigning_an_operator_who_lacks_the_role_the_service_requires(): void
    {
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        [$booking, $line] = $this->bookingWithRoleRestrictedLine($role);
        $unqualified = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Estil', 'first_name' => 'Estil', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->post(route('agenda.items.assign', [$booking, $line]), [
            'operator_id' => $unqualified->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull($line->fresh()->operator_id);
    }

    public function test_allows_assigning_an_operator_who_has_the_role_the_service_requires(): void
    {
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        [$booking, $line] = $this->bookingWithRoleRestrictedLine($role);
        $vet = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra', 'first_name' => 'Dra', 'is_active' => true]);
        $vet->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);

        $response = $this->actingAs($this->admin())->post(route('agenda.items.assign', [$booking, $line]), [
            'operator_id' => $vet->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame($vet->id, $line->fresh()->operator_id);
    }

    public function test_work_order_modal_only_offers_operators_qualified_for_that_line(): void
    {
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        [$booking] = $this->bookingWithRoleRestrictedLine($role);
        $vet = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra Calificada', 'first_name' => 'Dra', 'is_active' => true]);
        $vet->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);
        Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Sin Rol', 'first_name' => 'Sin', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Dra Calificada');
        $response->assertDontSee('Sin Rol');
    }

    public function test_assigns_operator_to_a_spa_booking_service_line(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->post(route('agenda.items.assign', [$booking, $line]), [
            'operator_id' => $operator->id,
        ]);

        $response->assertRedirect();
        $line->refresh();
        $this->assertSame($operator->id, $line->operator_id);
        $this->assertFalse($line->is_external);
        $this->assertNull($line->external_cost);
    }

    public function test_marks_line_as_external_with_cost_and_keeps_sale_price_independent(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine(price: 1000);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Vet Externo', 'first_name' => 'Vet', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->post(route('agenda.items.assign', [$booking, $line]), [
            'operator_id' => $operator->id,
            'is_external' => '1',
            'external_cost' => 600,
        ]);

        $response->assertRedirect();
        $line->refresh();
        $this->assertTrue($line->is_external);
        $this->assertEquals(600.0, (float) $line->external_cost);
        $this->assertEquals(1000.0, (float) $line->current_price); // el precio de venta no se mueve solo
    }

    public function test_sale_price_can_be_edited_independently_of_external_cost(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine(price: 1000);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Vet Externo', 'first_name' => 'Vet', 'is_active' => true]);

        // Costo estimado inicial
        $this->actingAs($this->admin())->post(route('agenda.items.assign', [$booking, $line]), [
            'operator_id' => $operator->id,
            'is_external' => '1',
            'external_cost' => 600,
        ]);

        // El proveedor cobró más — se corrige el costo real y, aparte, el precio de venta (decisión del staff, no automática)
        $response = $this->actingAs($this->admin())->post(route('agenda.items.assign', [$booking, $line]), [
            'operator_id' => $operator->id,
            'is_external' => '1',
            'external_cost' => 720,
            'current_price' => 1200,
        ]);

        $response->assertRedirect();
        $line->refresh();
        $this->assertEquals(720.0, (float) $line->external_cost);
        $this->assertEquals(1200.0, (float) $line->current_price);
    }

    public function test_cannot_assign_professional_to_a_line_from_another_booking(): void
    {
        [$bookingA] = $this->bookingWithServiceLine();
        [, $lineB] = $this->bookingWithServiceLine();
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->post(route('agenda.items.assign', [$bookingA, $lineB]), [
            'operator_id' => $operator->id,
        ]);

        $response->assertNotFound();
    }

    public function test_work_order_view_renders_the_assign_professional_modal_with_external_cost_fields(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine(price: 1000);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Vet Externo', 'first_name' => 'Vet', 'is_active' => true]);
        $line->update(['operator_id' => $operator->id, 'is_external' => true, 'external_cost' => 600]);

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Costo del proveedor externo');
        $response->assertSee('Precio de venta al cliente');
        $response->assertSee('Externo');
    }

    /**
     * SYNC-104/ZEUS-027: un servicio suspendido (`is_active = false`, ya usado antes — ver
     * `Service::hasHistoricalUsage()`) sigue mostrándose normal en una orden de trabajo ya
     * facturada, con un badge "Descontinuado" junto al nombre para que quede claro que ya no se
     * ofrece, sin que la línea histórica se vea afectada.
     */
    public function test_shows_a_discontinued_badge_when_the_service_is_no_longer_active(): void
    {
        [$booking, $line] = $this->bookingWithServiceLine();
        $line->service->update(['is_active' => false]);

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Descontinuado');
    }

    public function test_does_not_show_the_discontinued_badge_when_the_service_is_active(): void
    {
        [$booking] = $this->bookingWithServiceLine();

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertDontSee('Descontinuado');
    }
}
