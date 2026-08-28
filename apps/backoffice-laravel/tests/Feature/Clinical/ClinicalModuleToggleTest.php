<?php

namespace Tests\Feature\Clinical;

use App\Models\Client;
use App\Models\Pet;
use App\Models\User;
use App\Support\Navigation\MainNavigation;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class ClinicalModuleToggleTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    public function test_clinical_routes_are_blocked_when_the_module_is_disabled(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => false]);
        $pet = $this->pet();

        $response = $this->actingAs($this->admin())->get(route('clinical.pets.show', $pet));

        $response->assertNotFound();
    }

    public function test_clinical_routes_are_reachable_when_the_module_is_enabled(): void
    {
        Permission::firstOrCreate(['name' => 'ver clinico', 'guard_name' => 'web']);
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        $pet = $this->pet();
        $user = $this->admin();
        $user->givePermissionTo('ver clinico');

        $response = $this->actingAs($user)->get(route('clinical.pets.show', $pet));

        $response->assertOk();
    }

    /**
     * "Veterinaria" es grupo de nivel superior desde el 16/08/2026 (antes vivía anidada
     * dentro de "Operaciones del negocio" — ver `MainNavigation::structure()`).
     */
    private function allNavigationLabels(): Collection
    {
        return collect(MainNavigation::groups())->flatMap(function ($group) {
            return isset($group['subgroups'])
                ? collect($group['subgroups'])->pluck('label')
                : collect([$group['label']]);
        });
    }

    private function groupByLabel(string $label): ?array
    {
        return collect(MainNavigation::groups())->firstWhere('label', $label);
    }

    public function test_veterinaria_does_not_appear_in_navigation_when_disabled(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => false]);
        $this->actingAs($this->admin());

        $this->assertNotContains('Veterinaria', $this->allNavigationLabels());
    }

    public function test_veterinaria_appears_in_navigation_when_enabled(): void
    {
        Permission::firstOrCreate(['name' => 'ver clinico', 'guard_name' => 'web']);
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        $this->actingAs($this->admin());

        $this->assertContains('Veterinaria', $this->allNavigationLabels());
    }

    /**
     * Farmacia nunca fue una tabla aparte — son artículos del catálogo general (`items`,
     * department = 'Farmacia') a los que Veterinaria necesita llegar sin depender de que Tienda
     * esté activa (ver EnsureStoreOrClinicalModuleEnabled). Pedido explícito del usuario
     * (16/08/2026): "también debe manejar su farmacia dentro del catálogo de artículos general".
     */
    public function test_farmacia_link_appears_in_veterinaria_when_module_and_permission_are_present(): void
    {
        Permission::firstOrCreate(['name' => 'ver clinico', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ver catalogo_articulos', 'guard_name' => 'web']);
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        app(SystemSettings::class)->saveFields('store', ['store_module_enabled' => false]);
        $user = $this->admin();
        $user->givePermissionTo(['ver clinico', 'ver catalogo_articulos']);
        $this->actingAs($user);

        $veterinaria = $this->groupByLabel('Veterinaria');
        $this->assertNotNull($veterinaria);
        $this->assertTrue(collect($veterinaria['items'])->pluck('label')->contains('Farmacia'));
    }
}
