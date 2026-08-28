<?php

namespace Tests\Feature\Clinical;

use App\Models\Item;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Hallazgo real (16/08/2026, a pedido del usuario: "también debe manejar su 'farmacia' dentro
 * del catálogo de artículos general"): `items.*` vivía detrás de `store.module` únicamente — una
 * clínica con Veterinaria activa pero Tienda apagada no podía dar de alta ni ver sus propios
 * medicamentos (404 real), aunque el rol `veterinario` ya tenía el permiso `ver/crear
 * catalogo_articulos` desde `ClinicalRolesSeeder` (el bloqueo era de módulo, no de permiso).
 * Farmacia nunca fue una tabla separada — siempre fue `items` con `department = 'Farmacia'`
 * (ver BL-071); este fix solo abre la puerta de módulo, sin tocar el modelo de datos.
 */
class ClinicalPharmacyItemsAccessTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    public function test_items_index_is_reachable_when_only_clinical_module_is_enabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        Permission::firstOrCreate(['name' => 'ver catalogo_articulos', 'guard_name' => 'web']);
        $user = $this->createAdminUser();
        $user->givePermissionTo('ver catalogo_articulos');

        $response = $this->actingAs($user)->get(route('items.index'));

        $response->assertOk();
    }

    public function test_a_pharmacy_item_can_be_created_when_only_clinical_module_is_enabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        Permission::firstOrCreate(['name' => 'crear catalogo_articulos', 'guard_name' => 'web']);
        $user = $this->createAdminUser();
        $user->givePermissionTo('crear catalogo_articulos');

        $response = $this->actingAs($user)->post(route('items.store'), [
            'name' => 'Amoxicilina 250mg',
            'department' => 'Farmacia',
        ]);

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseHas('items', ['name' => 'Amoxicilina 250mg', 'department' => 'Farmacia']);
    }

    public function test_items_index_still_404s_when_neither_module_is_enabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => false]);
        Permission::firstOrCreate(['name' => 'ver catalogo_articulos', 'guard_name' => 'web']);
        $user = $this->createAdminUser();
        $user->givePermissionTo('ver catalogo_articulos');

        $response = $this->actingAs($user)->get(route('items.index'));

        $response->assertNotFound();
    }

    public function test_inventory_movements_still_require_store_module_even_with_clinical_enabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        Permission::firstOrCreate(['name' => 'editar catalogo_articulos', 'guard_name' => 'web']);
        $user = $this->createAdminUser();
        $user->givePermissionTo('editar catalogo_articulos');
        $item = Item::create(['name' => 'Amoxicilina', 'department' => 'Farmacia']);

        $response = $this->actingAs($user)->post(route('items.movements.store', $item), [
            'type' => 'entrada',
            'quantity' => 1,
        ]);

        $response->assertNotFound();
    }
}
