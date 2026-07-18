<?php

namespace Tests\Feature;

use App\Domain\Inventory\Contracts\ItemMovementServiceInterface;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * BL-049 (última pieza): transferencias entre sucursales — sin tabla `warehouses`,
 * `branch_id` ya es la unidad de ubicación. Una transferencia es un par de movimientos
 * ligados en el mismo ledger `item_movements`.
 */
class ItemTransferTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'Inventario Transfer Test',
            'first_name' => 'Inventario',
            'apellido_paterno' => 'Transfer',
            'email' => 'inventario-transfer-test-'.uniqid().'@example.com',
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

    public function test_a_transfer_moves_stock_from_one_branch_to_another_without_changing_the_global_total(): void
    {
        $movements = app(ItemMovementServiceInterface::class);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branchA = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);
        $branchB = Branch::create(['code' => 'SUC-2', 'name' => 'Sucursal Norte']);
        $movements->record(itemId: $item->id, type: 'entrada', quantity: 10, branchId: $branchA->id);

        [$out, $in] = $movements->transfer(itemId: $item->id, fromBranchId: $branchA->id, toBranchId: $branchB->id, quantity: 4);

        $this->assertSame(-4, $out->quantity);
        $this->assertSame($branchA->id, $out->branch_id);
        $this->assertSame(4, $in->quantity);
        $this->assertSame($branchB->id, $in->branch_id);
        $this->assertSame(ItemMovement::class, $in->reference_type);
        $this->assertSame($out->id, $in->reference_id);

        $this->assertDatabaseHas('item_branch_stocks', ['item_id' => $item->id, 'branch_id' => $branchA->id, 'quantity' => 6]);
        $this->assertDatabaseHas('item_branch_stocks', ['item_id' => $item->id, 'branch_id' => $branchB->id, 'quantity' => 4]);
        $this->assertSame(10, $item->fresh()->stock_quantity);
    }

    public function test_transfer_endpoint_registers_the_pair_of_movements(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branchA = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);
        $branchB = Branch::create(['code' => 'SUC-2', 'name' => 'Sucursal Norte']);
        app(ItemMovementServiceInterface::class)->record(itemId: $item->id, type: 'entrada', quantity: 10, branchId: $branchA->id);

        $response = $this->actingAs($user)->post(route('items.movements.transfer', $item), [
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'quantity' => 4,
        ]);

        $response->assertRedirect(route('items.edit', $item));
        $this->assertDatabaseHas('item_movements', ['item_id' => $item->id, 'branch_id' => $branchA->id, 'type' => 'transferencia_salida', 'quantity' => -4]);
        $this->assertDatabaseHas('item_movements', ['item_id' => $item->id, 'branch_id' => $branchB->id, 'type' => 'transferencia_entrada', 'quantity' => 4]);
    }

    public function test_a_transfer_to_the_same_branch_is_rejected_by_validation(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branch = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);

        $this->actingAs($user)->post(route('items.movements.transfer', $item), [
            'from_branch_id' => $branch->id,
            'to_branch_id' => $branch->id,
            'quantity' => 4,
        ])->assertSessionHasErrors('from_branch_id');

        $this->assertDatabaseCount('item_movements', 0);
    }

    public function test_a_transfer_can_leave_the_origin_branch_negative_same_as_any_other_movement(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branchA = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);
        $branchB = Branch::create(['code' => 'SUC-2', 'name' => 'Sucursal Norte']);

        $this->actingAs($user)->post(route('items.movements.transfer', $item), [
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'quantity' => 3,
        ]);

        $this->assertDatabaseHas('item_branch_stocks', ['item_id' => $item->id, 'branch_id' => $branchA->id, 'quantity' => -3]);
        $this->assertDatabaseHas('item_branch_stocks', ['item_id' => $item->id, 'branch_id' => $branchB->id, 'quantity' => 3]);
        $this->assertSame(0, $item->fresh()->stock_quantity);
    }

    public function test_a_user_without_the_permission_cannot_transfer(): void
    {
        $user = $this->userWithPermissions([]);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branchA = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);
        $branchB = Branch::create(['code' => 'SUC-2', 'name' => 'Sucursal Norte']);

        $this->actingAs($user)->post(route('items.movements.transfer', $item), [
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'quantity' => 3,
        ])->assertForbidden();

        $this->assertDatabaseCount('item_movements', 0);
    }

    public function test_the_item_edit_page_shows_the_transfer_form(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);

        $response = $this->actingAs($user)->get(route('items.edit', $item));

        $response->assertOk();
        $response->assertSee('Transferir entre sucursales');
    }
}
