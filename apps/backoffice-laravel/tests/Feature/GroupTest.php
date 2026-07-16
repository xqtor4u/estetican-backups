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

class GroupTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'Grupos Test',
            'first_name' => 'Grupos',
            'apellido_paterno' => 'Test',
            'email' => 'grupos-test-'.uniqid().'@example.com',
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

    public function test_a_user_can_create_list_edit_and_delete_a_group(): void
    {
        $user = $this->userWithPermissions([
            'ver catalogo_grupos', 'crear catalogo_grupos', 'editar catalogo_grupos', 'eliminar catalogo_grupos',
        ]);

        $storeResponse = $this->actingAs($user)->post(route('groups.store'), ['name' => 'Corte de cola de perro']);
        $storeResponse->assertRedirect();
        $group = Group::firstOrFail();

        $this->actingAs($user)->get(route('groups.index'))->assertOk()->assertSee('Corte de cola de perro');
        $this->actingAs($user)->get(route('groups.edit', $group))->assertOk();

        $this->actingAs($user)->put(route('groups.update', $group), ['name' => 'Corte de cola de perro', 'is_active' => '0'])
            ->assertRedirect();
        $this->assertFalse($group->fresh()->is_active);

        $this->actingAs($user)->delete(route('groups.destroy', $group))->assertRedirect();
        $this->assertModelMissing($group);
    }

    public function test_calculated_price_sums_components_by_quantity_times_unit_price(): void
    {
        $group = Group::create(['name' => 'Cirugía']);
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);
        $item = Item::create(['name' => 'Venda', 'price' => 10]);

        GroupComponent::create(['group_id' => $group->id, 'service_id' => $service->id, 'quantity' => 0.5]);
        GroupComponent::create(['group_id' => $group->id, 'item_id' => $item->id, 'quantity' => 5]);

        // 0.5 * 100 + 5 * 10 = 50 + 50 = 100
        $this->assertSame(100.0, $group->fresh()->load('components')->calculatedPrice());
    }

    public function test_calculated_price_reflects_live_catalog_price_changes(): void
    {
        $group = Group::create(['name' => 'Cirugía']);
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);
        GroupComponent::create(['group_id' => $group->id, 'service_id' => $service->id, 'quantity' => 1]);

        $this->assertSame(100.0, $group->fresh()->load('components')->calculatedPrice());

        $service->update(['price' => 200]);

        $this->assertSame(200.0, $group->fresh()->load('components')->calculatedPrice());
    }

    public function test_a_user_without_the_permission_cannot_reach_the_groups_screens(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get(route('groups.index'))->assertForbidden();
        $this->actingAs($user)->get(route('groups.create'))->assertForbidden();
        $this->actingAs($user)->post(route('groups.store'), ['name' => 'X'])->assertForbidden();
    }
}
