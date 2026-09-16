<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BaseRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * EST-019 (auditoría QA/UX 14/09/2026) — antes de este fix, `/` siempre redirigía a
 * `dashboard.index`, que exige `permission:ver dashboard`. Un usuario válido con permisos
 * para otras secciones (agenda, clientes, etc.) pero sin ese permiso puntual quedaba
 * atrapado en la pantalla de "Acceso restringido" justo después de iniciar sesión, sin
 * ninguna salida real hacia las secciones a las que sí tenía acceso.
 */
class HomeRedirectFallbackTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_user_with_dashboard_permission_still_lands_on_dashboard(): void
    {
        (new BaseRolesSeeder)->run();

        $admin = $this->makeUser(['role' => 'operator']);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('home'))
            ->assertRedirect(route('dashboard.index'));
    }

    public function test_user_without_dashboard_permission_lands_on_first_accessible_section(): void
    {
        (new BaseRolesSeeder)->run();

        $role = Role::firstOrCreate(['name' => 'solo-agenda', 'guard_name' => 'web']);
        $role->syncPermissions(['ver agenda']);

        $user = $this->makeUser(['role' => 'operator']);
        $user->assignRole($role);

        $this->actingAs($user)->get(route('home'))
            ->assertRedirect(route('agenda.index'));
    }

    public function test_user_without_any_permission_lands_on_own_settings_not_a_403_trap(): void
    {
        (new BaseRolesSeeder)->run();

        // Sin rol Spatie asignado — ningún permiso, ni siquiera `ver dashboard`.
        $user = $this->makeUser(['role' => 'operator']);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('user.settings'));
        $this->followRedirects($response)->assertOk();
    }

    public function test_guest_is_still_sent_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }
}
