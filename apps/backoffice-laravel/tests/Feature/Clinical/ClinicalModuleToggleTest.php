<?php

namespace Tests\Feature\Clinical;

use App\Models\Client;
use App\Models\Pet;
use App\Models\User;
use App\Support\Navigation\MainNavigation;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClinicalModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-clinical-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
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

    public function test_veterinaria_does_not_appear_in_navigation_when_disabled(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => false]);
        $this->actingAs($this->admin());

        $groups = collect(MainNavigation::groups())->pluck('label');

        $this->assertNotContains('Veterinaria', $groups);
    }

    public function test_veterinaria_appears_in_navigation_when_enabled(): void
    {
        Permission::firstOrCreate(['name' => 'ver clinico', 'guard_name' => 'web']);
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        $this->actingAs($this->admin());

        $groups = collect(MainNavigation::groups())->pluck('label');

        $this->assertContains('Veterinaria', $groups);
    }
}
