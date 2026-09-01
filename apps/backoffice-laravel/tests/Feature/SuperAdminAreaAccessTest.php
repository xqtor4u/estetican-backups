<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BaseRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El área de administración del sistema (Usuarios, Configuración, Bitácora de actividad,
 * Finanzas) debe abrirse para cualquier super admin según `User::is_super_admin` — rol
 * Spatie `admin`/`super-admin` **o** la columna legacy `users.role = 'admin'`. Antes el
 * grupo usaba `role:admin|super-admin` (solo Spatie) y un super admin sin el rol Spatie
 * asignado (aprovisionamiento de tenant, orden de seeders, alta directa en BD) veía el
 * menú pero recibía 403 al entrar.
 */
class SuperAdminAreaAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function adminRoutes(User $someUser): array
    {
        return [
            route('users.index'),
            route('users.edit', $someUser),
            route('system-settings.index'),
            route('activity-log.index'),
            route('finances.accounts.index'),
        ];
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'U '.uniqid(),
            'first_name' => 'U',
            'apellido_paterno' => 'Test',
            'email' => 'u-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ], $overrides));
    }

    public function test_legacy_only_admin_reaches_the_admin_area(): void
    {
        (new BaseRolesSeeder)->run();

        // Super admin SOLO por la columna legacy — sin assignRole('admin').
        $legacyAdmin = $this->makeUser(['role' => 'admin']);
        $this->assertTrue($legacyAdmin->is_super_admin);
        $this->assertFalse($legacyAdmin->hasRole('admin'));

        foreach ($this->adminRoutes($legacyAdmin) as $url) {
            $this->actingAs($legacyAdmin)->get($url)->assertOk();
        }
    }

    public function test_spatie_admin_still_reaches_the_admin_area(): void
    {
        (new BaseRolesSeeder)->run();

        $admin = $this->makeUser(['role' => 'admin']);
        $admin->assignRole('admin');

        foreach ($this->adminRoutes($admin) as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_operator_is_denied_the_admin_area(): void
    {
        (new BaseRolesSeeder)->run();

        $operator = $this->makeUser(['role' => 'operator']);
        $this->assertFalse($operator->is_super_admin);

        foreach ($this->adminRoutes($operator) as $url) {
            $this->actingAs($operator)->get($url)->assertForbidden();
        }
    }

    public function test_edit_guard_lets_a_legacy_admin_edit_another_user(): void
    {
        (new BaseRolesSeeder)->run();

        $legacyAdmin = $this->makeUser(['role' => 'admin']);
        $target = $this->makeUser(['role' => 'operator']);

        $this->actingAs($legacyAdmin)
            ->get(route('users.edit', $target))
            ->assertOk();
    }
}
