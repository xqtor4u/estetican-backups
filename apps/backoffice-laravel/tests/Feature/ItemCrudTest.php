<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Item;
use App\Models\Pet;
use App\Models\PetVaccination;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ItemCrudTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'Catalogo Test',
            'first_name' => 'Catalogo',
            'last_name' => 'Test',
            'email' => 'catalogo-item-test-'.uniqid().'@example.com',
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

    public function test_a_user_can_create_list_edit_and_delete_an_item(): void
    {
        $user = $this->userWithPermissions([
            'ver catalogo_articulos',
            'crear catalogo_articulos',
            'editar catalogo_articulos',
            'eliminar catalogo_articulos',
        ]);

        $storeResponse = $this->actingAs($user)->post(route('items.store'), [
            'name' => 'Vacuna Antirrábica',
            'brand' => 'Nobivac',
            'presentation' => 'Frasco 1 dosis',
            'department' => 'Farmacia',
        ]);

        $storeResponse->assertRedirect(route('items.index'));
        $item = Item::firstOrFail();

        $indexResponse = $this->actingAs($user)->get(route('items.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Vacuna Antirrábica');
        $indexResponse->assertSee('Nobivac');

        $editResponse = $this->actingAs($user)->get(route('items.edit', $item));
        $editResponse->assertOk();
        $editResponse->assertSee('Vacuna Antirrábica');

        $updateResponse = $this->actingAs($user)->put(route('items.update', $item), [
            'name' => 'Vacuna Antirrábica',
            'brand' => 'Nobivac',
            'presentation' => 'Frasco 1 dosis',
            'department' => 'Farmacia',
            'is_active' => '0',
        ]);
        $updateResponse->assertRedirect(route('items.index'));
        $this->assertFalse($item->fresh()->is_active);

        $destroyResponse = $this->actingAs($user)->delete(route('items.destroy', $item));
        $destroyResponse->assertRedirect(route('items.index'));
        $this->assertModelMissing($item);
    }

    public function test_a_user_without_the_permission_cannot_reach_the_items_screens(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)->get(route('items.index'))->assertForbidden();
        $this->actingAs($user)->get(route('items.create'))->assertForbidden();
        $this->actingAs($user)->post(route('items.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_deleting_an_item_keeps_the_historical_vaccination_with_its_manufacturer_snapshot(): void
    {
        $user = $this->userWithPermissions(['eliminar catalogo_articulos']);
        $item = Item::create(['name' => 'Vacuna Antirrábica', 'brand' => 'Nobivac']);
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create([
            'code' => 'VAC-'.uniqid(), 'type' => 'vaccine', 'name' => 'Vacuna Rabia',
            'price' => 0, 'duration_minutes' => 10, 'is_active' => true,
        ]);
        $vaccination = PetVaccination::create([
            'pet_id' => $pet->id,
            'service_id' => $service->id,
            'item_id' => $item->id,
            'vaccine_name' => $service->name,
            'manufacturer' => $item->brand,
        ]);

        $this->actingAs($user)->delete(route('items.destroy', $item))->assertRedirect(route('items.index'));

        $this->assertModelMissing($item);
        $vaccination->refresh();
        $this->assertNull($vaccination->item_id);
        $this->assertSame('Nobivac', $vaccination->manufacturer);
    }
}
