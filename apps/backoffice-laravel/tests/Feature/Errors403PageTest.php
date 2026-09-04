<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BaseRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SYNC-081 — cualquier 403 del backoffice web debe renderizar `errors/403.blade.php`
 * (mensaje "Acceso restringido para este usuario" + acción "Cambiar de usuario"), no
 * la página cruda de Laravel con el texto en inglés de Spatie. Cubre las dos vías de
 * 403 del motor: el middleware `permission:` de Spatie (UnauthorizedException) y el
 * `abort(403, ...)` del middleware `superadmin` (EnsureSuperAdmin).
 */
class Errors403PageTest extends TestCase
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

    public function test_spatie_permission_403_renders_the_custom_page(): void
    {
        (new BaseRolesSeeder)->run();

        // Operador sin permiso `ver dashboard` — es donde aterriza el login.
        $operator = $this->makeUser(['role' => 'operator']);

        $response = $this->actingAs($operator)->get(route('dashboard.index'));

        $response->assertForbidden();
        $response->assertSee('Acceso restringido para este usuario');
        $response->assertSee('Cambiar de usuario');
        $response->assertDontSee('User does not have the right permissions.');
    }

    public function test_superadmin_abort_403_renders_the_custom_page(): void
    {
        (new BaseRolesSeeder)->run();

        $operator = $this->makeUser(['role' => 'operator']);

        $response = $this->actingAs($operator)->get(route('users.index'));

        $response->assertForbidden();
        $response->assertSee('Acceso restringido para este usuario');
        $response->assertDontSee('Solo un administrador del sistema puede acceder');
    }

    public function test_custom_page_shows_a_logout_form_to_switch_user(): void
    {
        (new BaseRolesSeeder)->run();

        $operator = $this->makeUser(['role' => 'operator']);

        $response = $this->actingAs($operator)->get(route('dashboard.index'));

        $response->assertForbidden();
        $response->assertSee('action="'.route('logout').'"', false);
        $response->assertSee($operator->email);
    }

    public function test_admin_is_not_affected(): void
    {
        (new BaseRolesSeeder)->run();

        $admin = $this->makeUser(['role' => 'operator']);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('dashboard.index'))->assertOk();
    }
}
