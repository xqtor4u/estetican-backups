<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupComponent;
use App\Models\Item;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GroupComponentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Componentes Test',
            'first_name' => 'Componentes',
            'apellido_paterno' => 'Test',
            'email' => 'componentes-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ]);

        Permission::firstOrCreate(['name' => 'editar catalogo_grupos', 'guard_name' => 'web']);
        $user->givePermissionTo(['editar catalogo_grupos']);

        return $user;
    }

    public function test_can_add_a_service_component(): void
    {
        $group = Group::create(['name' => 'Cirugía']);
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);

        $this->actingAs($this->admin())->post(route('groups.components.store', $group), [
            'service_id' => $service->id,
            'quantity' => 0.5,
        ])->assertRedirect();

        $this->assertDatabaseHas('group_components', ['group_id' => $group->id, 'service_id' => $service->id, 'quantity' => 0.5]);
    }

    public function test_can_add_an_item_component(): void
    {
        $group = Group::create(['name' => 'Cirugía']);
        $item = Item::create(['name' => 'Venda', 'price' => 10]);

        $this->actingAs($this->admin())->post(route('groups.components.store', $group), [
            'item_id' => $item->id,
            'quantity' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('group_components', ['group_id' => $group->id, 'item_id' => $item->id, 'quantity' => 5]);
    }

    public function test_rejects_both_service_and_item_at_once(): void
    {
        $group = Group::create(['name' => 'Cirugía']);
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);
        $item = Item::create(['name' => 'Venda', 'price' => 10]);

        $this->actingAs($this->admin())->post(route('groups.components.store', $group), [
            'service_id' => $service->id,
            'item_id' => $item->id,
            'quantity' => 1,
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('group_components', 0);
    }

    public function test_rejects_neither_service_nor_item(): void
    {
        $group = Group::create(['name' => 'Cirugía']);

        $this->actingAs($this->admin())->post(route('groups.components.store', $group), [
            'quantity' => 1,
        ])->assertSessionHasErrors();

        $this->assertDatabaseCount('group_components', 0);
    }

    public function test_can_remove_a_component_and_price_drops(): void
    {
        $group = Group::create(['name' => 'Cirugía']);
        $item = Item::create(['name' => 'Venda', 'price' => 10]);
        $component = GroupComponent::create(['group_id' => $group->id, 'item_id' => $item->id, 'quantity' => 5]);

        $this->assertSame(50.0, $group->fresh()->load('components')->calculatedPrice());

        $this->actingAs($this->admin())->delete(route('groups.components.destroy', [$group, $component]))->assertRedirect();

        $this->assertModelMissing($component);
        $this->assertSame(0.0, $group->fresh()->load('components')->calculatedPrice());
    }

    public function test_cannot_delete_an_item_used_as_a_group_component(): void
    {
        $group = Group::create(['name' => 'Cirugía']);
        $item = Item::create(['name' => 'Venda', 'price' => 10]);
        GroupComponent::create(['group_id' => $group->id, 'item_id' => $item->id, 'quantity' => 5]);

        Permission::firstOrCreate(['name' => 'eliminar catalogo_articulos', 'guard_name' => 'web']);
        $user = $this->admin();
        $user->givePermissionTo(['eliminar catalogo_articulos']);

        $this->actingAs($user)->delete(route('items.destroy', $item))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertModelExists($item);
    }
}
