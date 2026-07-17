<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Group;
use App\Models\Item;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\Navigation\MainNavigation;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StoreModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Store Module Test',
            'first_name' => 'Store',
            'apellido_paterno' => 'Test',
            'email' => 'store-module-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function booking(): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);
    }

    /**
     * "Artículos" y "Grupos" son ítems dentro de los grupos "Inventario" (subgrupo de
     * Administración) y "Catálogos" respectivamente, no labels de grupo/subgrupo en sí
     * — a diferencia de "Veterinaria", que sí es un label de grupo completo.
     */
    private function allNavigationItemLabels(): \Illuminate\Support\Collection
    {
        return collect(MainNavigation::groups())->flatMap(function ($group) {
            $subgroups = $group['subgroups'] ?? [$group];

            return collect($subgroups)->flatMap(fn ($subgroup) => collect($subgroup['items'] ?? [])->pluck('label'));
        });
    }

    public function test_items_and_groups_routes_are_blocked_when_the_module_is_disabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);

        $response = $this->actingAs($this->admin())->get(route('items.index'));
        $response->assertNotFound();

        $response = $this->actingAs($this->admin())->get(route('groups.index'));
        $response->assertNotFound();
    }

    public function test_items_and_groups_routes_are_reachable_when_the_module_is_enabled(): void
    {
        Permission::firstOrCreate(['name' => 'ver catalogo_articulos', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ver catalogo_grupos', 'guard_name' => 'web']);
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => true]);
        $user = $this->admin();
        $user->givePermissionTo(['ver catalogo_articulos', 'ver catalogo_grupos']);

        $this->actingAs($user)->get(route('items.index'))->assertOk();
        $this->actingAs($user)->get(route('groups.index'))->assertOk();
    }

    public function test_articulos_and_grupos_do_not_appear_in_navigation_when_disabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        $this->actingAs($this->admin());

        $labels = $this->allNavigationItemLabels();
        $this->assertNotContains('Artículos', $labels);
        $this->assertNotContains('Grupos', $labels);
    }

    public function test_articulos_and_grupos_appear_in_navigation_when_enabled(): void
    {
        Permission::firstOrCreate(['name' => 'ver catalogo_articulos', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ver catalogo_grupos', 'guard_name' => 'web']);
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => true]);
        $user = $this->admin();
        $user->givePermissionTo(['ver catalogo_articulos', 'ver catalogo_grupos']);
        $this->actingAs($user);

        $labels = $this->allNavigationItemLabels();
        $this->assertContains('Artículos', $labels);
        $this->assertContains('Grupos', $labels);
    }

    public function test_quote_manager_hides_group_and_item_pickers_when_the_module_is_disabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        $booking = $this->booking();

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertDontSee('Agregar grupo completo');
        $response->assertDontSee('Agregar artículo suelto');
        $response->assertSee('Agregar servicio adicional');
    }

    public function test_quote_manager_shows_group_and_item_pickers_when_the_module_is_enabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => true]);
        $booking = $this->booking();

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Agregar grupo completo');
        $response->assertSee('Agregar artículo suelto');
    }

    public function test_storing_a_quote_with_an_item_line_is_rejected_when_the_module_is_disabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        $booking = $this->booking();
        $item = Item::create(['name' => 'Venda', 'price' => 10]);

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1, 'price' => 10],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_storing_a_quote_with_a_group_line_is_rejected_when_the_module_is_disabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);
        $group = Group::create(['name' => 'Cirugía']);

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1, 'price' => 100, 'group_id' => $group->id],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_storing_a_service_only_quote_still_works_when_the_module_is_disabled(): void
    {
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        $booking = $this->booking();
        $service = Service::create(['code' => 'VET-'.uniqid(), 'name' => 'Veterinario', 'type' => 'extra', 'price' => 100, 'duration_minutes' => 60]);

        $response = $this->actingAs($this->admin())->post(route('agenda.quotes.store', $booking), [
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1, 'price' => 100],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('quotes', 1);
        $this->assertEquals(100.0, (float) Quote::firstOrFail()->total_amount);
    }
}
