<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Item;
use App\Models\ItemMovement;
use App\Models\Operator;
use App\Models\OperatorBranchAssignment;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\SpaBookingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * BL-049 (última pieza): al completarse una cita, sus `spa_booking_items` deben descontarse
 * del inventario en la sucursal primaria del operador asignado. Cubre los 3 puntos reales
 * donde una cita pasa a `completed` (ver NT-020: no hay ningún hook/evento centralizado).
 */
class BookingStockConsumptionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser(['email' => 'admin-stock-test-'.uniqid().'@example.com']);
    }

    private function apiHeaders(): array
    {
        $user = $this->admin();
        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['Authorization' => "Bearer {$plainToken}"];
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function operatorWithPrimaryBranch(): array
    {
        $branch = Branch::create(['code' => 'SUC-'.uniqid(), 'name' => 'Sucursal Centro']);
        $operator = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        OperatorBranchAssignment::create(['operator_id' => $operator->id, 'branch_id' => $branch->id, 'is_primary' => true]);

        return [$operator, $branch];
    }

    private function bookingWithItem(Operator $operator, int $quantity = 3): array
    {
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $booking = SpaBooking::create([
            'pet_id' => $this->pet()->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);
        $bookingItem = $booking->items()->create(['item_id' => $item->id, 'quantity' => $quantity, 'current_price' => $quantity * 10]);

        return [$booking, $item, $bookingItem];
    }

    public function test_completing_a_booking_via_the_web_work_order_consumes_stock_at_the_operators_primary_branch(): void
    {
        [$operator, $branch] = $this->operatorWithPrimaryBranch();
        [$booking, $item, $bookingItem] = $this->bookingWithItem($operator, 3);

        $response = $this->actingAs($this->admin())->put(route('agenda.update', $booking), ['status' => 'completed']);

        $response->assertRedirect(route('agenda.show', $booking));
        $this->assertDatabaseHas('item_movements', [
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'type' => 'consumo_servicio',
            'quantity' => -3,
            'reference_type' => SpaBookingItem::class,
            'reference_id' => $bookingItem->id,
        ]);
        $this->assertSame(-3, $item->fresh()->stock_quantity);
        $this->assertDatabaseHas('item_branch_stocks', ['item_id' => $item->id, 'branch_id' => $branch->id, 'quantity' => -3]);
    }

    public function test_completing_an_already_completed_booking_does_not_double_consume_stock(): void
    {
        [$operator] = $this->operatorWithPrimaryBranch();
        [$booking, $item] = $this->bookingWithItem($operator, 3);

        $admin = $this->admin();
        $this->actingAs($admin)->put(route('agenda.update', $booking), ['status' => 'completed']);
        $this->actingAs($admin)->put(route('agenda.update', $booking), ['status' => 'completed']);

        $this->assertSame(1, ItemMovement::where('item_id', $item->id)->where('type', 'consumo_servicio')->count());
        $this->assertSame(-3, $item->fresh()->stock_quantity);
    }

    public function test_completing_a_booking_whose_operator_has_no_primary_branch_only_updates_global_stock(): void
    {
        $operator = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        [$booking, $item] = $this->bookingWithItem($operator, 2);

        $this->actingAs($this->admin())->put(route('agenda.update', $booking), ['status' => 'completed']);

        $this->assertSame(-2, $item->fresh()->stock_quantity);
        $this->assertDatabaseCount('item_branch_stocks', 0);
    }

    public function test_completing_a_booking_via_the_mobile_payment_endpoint_consumes_stock(): void
    {
        [$operator, $branch] = $this->operatorWithPrimaryBranch();
        [$booking, $item] = $this->bookingWithItem($operator, 1);

        $response = $this->withHeaders($this->apiHeaders())->postJson("/api/bookings/{$booking->id}/payments", [
            'amount' => 100,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
        ]);

        $response->assertOk();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertDatabaseHas('item_movements', [
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'type' => 'consumo_servicio',
            'quantity' => -1,
        ]);
    }

    public function test_completing_a_booking_via_the_generic_mobile_update_endpoint_consumes_stock(): void
    {
        [$operator, $branch] = $this->operatorWithPrimaryBranch();
        [$booking, $item] = $this->bookingWithItem($operator, 4);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->id}", [
            'status' => 'completed',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('item_movements', [
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'type' => 'consumo_servicio',
            'quantity' => -4,
        ]);
    }
}
