<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignProfessionalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Assign Professional Test',
            'first_name' => 'Assign',
            'apellido_paterno' => 'Test',
            'email' => 'assign-professional-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
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

        return [$booking, $line];
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
}
