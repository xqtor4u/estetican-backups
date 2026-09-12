<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Group;
use App\Models\GroupComponent;
use App\Models\Item;
use App\Models\Pet;
use App\Models\PetVaccination;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingItem;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
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
            'apellido_paterno' => 'Test',
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

    public function test_cost_price_is_saved_and_the_configured_margin_is_shown_on_create(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_profit_margin_percentage' => 40]);
        $user = $this->userWithPermissions(['ver catalogo_articulos', 'crear catalogo_articulos']);

        $createResponse = $this->actingAs($user)->get(route('items.create'));
        $createResponse->assertOk()->assertSee('40%');

        $storeResponse = $this->actingAs($user)->post(route('items.store'), [
            'name' => 'Shampoo hipoalergénico',
            'cost_price' => '50.00',
            'price' => '70.00',
        ]);
        $storeResponse->assertRedirect(route('items.index'));

        $this->assertDatabaseHas('items', [
            'name' => 'Shampoo hipoalergénico',
            'cost_price' => 50.00,
            'price' => 70.00,
        ]);
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

    /**
     * SYNC-104/ZEUS-027: `quote_items.item_id` es `cascadeOnDelete()` — borrar un artículo que ya
     * aparece en un presupuesto real borraría esa línea histórica en cascada. En vez de eso,
     * `destroy()` lo suspende (`is_active = false`, ya lo saca de todos los selectores de alta).
     */
    public function test_deleting_an_item_referenced_by_a_quote_item_suspends_it_instead_of_deleting(): void
    {
        $user = $this->userWithPermissions(['eliminar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo', 'is_active' => true]);
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now(), 'duration_minutes' => 30, 'status' => 'scheduled', 'total_estimated_price' => 100]);
        $quote = Quote::create(['spa_booking_id' => $booking->id, 'version_label' => 'v1', 'status' => 'draft', 'total_amount' => 100]);
        // Constraint chk_quote_item_target: una línea de presupuesto es de servicio O de
        // artículo, nunca ambos — service_id se deja null a propósito.
        $quoteItem = QuoteItem::create(['quote_id' => $quote->id, 'item_id' => $item->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->delete(route('items.destroy', $item));

        $response->assertRedirect(route('items.index'));
        $this->assertModelExists($item);
        $this->assertFalse($item->fresh()->is_active);
        $this->assertModelExists($quoteItem);
    }

    /**
     * SYNC-104/ZEUS-027: mismo caso que arriba, pero con `spa_booking_items.item_id`
     * (`cascadeOnDelete()`), la referencia real de un artículo consumido en una cita.
     */
    public function test_deleting_an_item_referenced_by_a_spa_booking_item_suspends_it_instead_of_deleting(): void
    {
        $user = $this->userWithPermissions(['eliminar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo', 'is_active' => true]);
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'scheduled_at' => now(), 'duration_minutes' => 30, 'status' => 'completed', 'total_estimated_price' => 100]);
        $bookingItem = SpaBookingItem::create(['spa_booking_id' => $booking->id, 'item_id' => $item->id, 'quantity' => 1, 'current_price' => 50]);

        $response = $this->actingAs($user)->delete(route('items.destroy', $item));

        $response->assertRedirect(route('items.index'));
        $this->assertModelExists($item);
        $this->assertFalse($item->fresh()->is_active);
        $this->assertModelExists($bookingItem);
    }

    /**
     * SYNC-104/ZEUS-027: ser componente de un Grupo también cuenta como "en uso" — antes
     * bloqueaba con un mensaje de error aparte, ahora se unifica con el resto: se suspende en vez
     * de bloquear. El grupo sigue funcionando igual (no filtra componentes por `is_active` al
     * leerlos), solo deja de poder elegirse este artículo para grupos nuevos.
     */
    public function test_deleting_an_item_that_is_a_group_component_suspends_it_instead_of_deleting(): void
    {
        $user = $this->userWithPermissions(['eliminar catalogo_articulos']);
        $item = Item::create(['name' => 'Shampoo', 'is_active' => true]);
        $group = Group::create(['name' => 'Combo baño', 'is_active' => true]);
        $component = GroupComponent::create(['group_id' => $group->id, 'item_id' => $item->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->delete(route('items.destroy', $item));

        $response->assertRedirect(route('items.index'));
        $this->assertModelExists($item);
        $this->assertFalse($item->fresh()->is_active);
        $this->assertModelExists($component);
    }
}
