<?php

namespace Tests\Feature;

use App\Domain\Inventory\Contracts\ItemMovementServiceInterface;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ItemMovementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'Inventario Test',
            'first_name' => 'Inventario',
            'apellido_paterno' => 'Test',
            'email' => 'inventario-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_an_entrada_movement_increases_stock(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branch = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);

        $response = $this->actingAs($user)->post(route('items.movements.store', $item), [
            'type' => 'entrada',
            'quantity' => 10,
            'branch_id' => $branch->id,
        ]);

        $response->assertRedirect(route('items.edit', $item));
        $this->assertSame(10, $item->fresh()->stock_quantity);
        $this->assertDatabaseHas('item_movements', [
            'item_id' => $item->id,
            'type' => 'entrada',
            'quantity' => 10,
        ]);
    }

    public function test_a_perdida_movement_decreases_stock_and_can_go_negative_is_not_allowed_by_business_but_math_is_correct(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branch = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);
        app(ItemMovementServiceInterface::class)->record(itemId: $item->id, type: 'entrada', quantity: 5);

        $this->actingAs($user)->post(route('items.movements.store', $item), [
            'type' => 'perdida',
            'quantity' => 2,
            'branch_id' => $branch->id,
        ]);

        $this->assertSame(3, $item->fresh()->stock_quantity);
    }

    public function test_an_ajuste_movement_respects_the_chosen_direction(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branch = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);
        app(ItemMovementServiceInterface::class)->record(itemId: $item->id, type: 'entrada', quantity: 5);

        $this->actingAs($user)->post(route('items.movements.store', $item), [
            'type' => 'ajuste',
            'quantity' => 2,
            'direction' => 'resta',
            'branch_id' => $branch->id,
        ]);

        $this->assertSame(3, $item->fresh()->stock_quantity);

        $this->actingAs($user)->post(route('items.movements.store', $item), [
            'type' => 'ajuste',
            'quantity' => 4,
            'direction' => 'suma',
            'branch_id' => $branch->id,
        ]);

        $this->assertSame(7, $item->fresh()->stock_quantity);
    }

    public function test_a_movement_can_be_tied_to_a_branch(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branch = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);

        $this->actingAs($user)->post(route('items.movements.store', $item), [
            'type' => 'entrada',
            'quantity' => 10,
            'branch_id' => $branch->id,
        ]);

        $this->assertDatabaseHas('item_movements', [
            'item_id' => $item->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_a_movement_without_branch_is_rejected_by_validation(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);

        $this->actingAs($user)->post(route('items.movements.store', $item), [
            'type' => 'entrada',
            'quantity' => 10,
        ])->assertSessionHasErrors('branch_id');

        $this->assertSame(0, $item->fresh()->stock_quantity);
    }

    public function test_a_user_without_the_permission_cannot_register_a_movement(): void
    {
        $user = $this->userWithPermissions([]);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branch = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);

        $this->actingAs($user)->post(route('items.movements.store', $item), [
            'type' => 'entrada',
            'quantity' => 10,
            'branch_id' => $branch->id,
        ])->assertForbidden();

        $this->assertSame(0, $item->fresh()->stock_quantity);
    }

    public function test_concurrent_style_sequential_movements_keep_an_accurate_cached_balance(): void
    {
        $service = app(ItemMovementServiceInterface::class);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);

        $service->record(itemId: $item->id, type: 'entrada', quantity: 10);
        $service->record(itemId: $item->id, type: 'salida', quantity: -3);
        $service->record(itemId: $item->id, type: 'consumo_servicio', quantity: -1);
        $service->record(itemId: $item->id, type: 'ajuste', quantity: 2);

        $this->assertSame(8, $item->fresh()->stock_quantity);
        $this->assertSame(8, (int) \App\Models\ItemMovement::where('item_id', $item->id)->sum('quantity'));
    }
}
