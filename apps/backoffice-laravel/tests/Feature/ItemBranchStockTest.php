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

class ItemBranchStockTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'Inventario Sucursal Test',
            'first_name' => 'Inventario',
            'apellido_paterno' => 'Sucursal',
            'email' => 'inventario-sucursal-test-'.uniqid().'@example.com',
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

    public function test_a_movement_with_branch_updates_the_per_branch_cached_balance(): void
    {
        $service = app(ItemMovementServiceInterface::class);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branch = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);

        $service->record(itemId: $item->id, type: 'entrada', quantity: 10, branchId: $branch->id);

        $this->assertDatabaseHas('item_branch_stocks', [
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'quantity' => 10,
        ]);
    }

    public function test_movements_across_two_branches_keep_independent_balances_that_sum_to_the_global_total(): void
    {
        $service = app(ItemMovementServiceInterface::class);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branchA = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);
        $branchB = Branch::create(['code' => 'SUC-2', 'name' => 'Sucursal Norte']);

        $service->record(itemId: $item->id, type: 'entrada', quantity: 10, branchId: $branchA->id);
        $service->record(itemId: $item->id, type: 'entrada', quantity: 4, branchId: $branchB->id);
        $service->record(itemId: $item->id, type: 'salida', quantity: -3, branchId: $branchA->id);

        $this->assertDatabaseHas('item_branch_stocks', [
            'item_id' => $item->id,
            'branch_id' => $branchA->id,
            'quantity' => 7,
        ]);
        $this->assertDatabaseHas('item_branch_stocks', [
            'item_id' => $item->id,
            'branch_id' => $branchB->id,
            'quantity' => 4,
        ]);
        $this->assertSame(11, $item->fresh()->stock_quantity);
    }

    public function test_a_movement_without_branch_does_not_create_a_branch_stock_row(): void
    {
        $service = app(ItemMovementServiceInterface::class);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);

        $service->record(itemId: $item->id, type: 'consumo_servicio', quantity: -1);

        $this->assertDatabaseCount('item_branch_stocks', 0);
        $this->assertSame(-1, $item->fresh()->stock_quantity);
    }

    public function test_sequential_movements_on_the_same_item_and_branch_keep_an_accurate_cached_balance(): void
    {
        $service = app(ItemMovementServiceInterface::class);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branch = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);

        $service->record(itemId: $item->id, type: 'entrada', quantity: 10, branchId: $branch->id);
        $service->record(itemId: $item->id, type: 'salida', quantity: -3, branchId: $branch->id);
        $service->record(itemId: $item->id, type: 'ajuste', quantity: 2, branchId: $branch->id);

        $this->assertDatabaseHas('item_branch_stocks', [
            'item_id' => $item->id,
            'branch_id' => $branch->id,
            'quantity' => 9,
        ]);
        $this->assertSame(9, (int) ItemMovement::where('item_id', $item->id)->where('branch_id', $branch->id)->sum('quantity'));
    }

    public function test_the_item_edit_page_shows_the_stock_breakdown_by_branch(): void
    {
        $user = $this->userWithPermissions(['editar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo hipoalergénico']);
        $branch = Branch::create(['code' => 'SUC-1', 'name' => 'Sucursal Centro']);
        app(ItemMovementServiceInterface::class)->record(itemId: $item->id, type: 'entrada', quantity: 6, branchId: $branch->id);
        app(ItemMovementServiceInterface::class)->record(itemId: $item->id, type: 'consumo_servicio', quantity: -1);

        $response = $this->actingAs($user)->get(route('items.edit', $item));

        $response->assertOk();
        $response->assertSee('Sucursal Centro');
        $response->assertSee('Sin sucursal / otras');
    }
}
