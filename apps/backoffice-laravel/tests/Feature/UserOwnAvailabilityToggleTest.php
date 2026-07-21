<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserOwnAvailabilityToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ver disponibilidad_propia', 'crear disponibilidad_propia', 'eliminar disponibilidad_propia', 'ver agenda', 'crear agenda'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-own-avail-toggle-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole($role);

        return $admin;
    }

    private function targetUser(): User
    {
        return User::create([
            'name' => 'Operador Test',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test',
            'email' => 'operador-own-avail-toggle-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function baseFields(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
            'is_operator' => false,
        ];
    }

    public function test_edit_page_shows_the_toggle_unchecked_by_default(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();

        $response = $this->actingAs($admin)->get(route('users.edit', $user));

        $response->assertOk();
        $response->assertSee('Puede bloquear su propia disponibilidad');
        $response->assertDontSee('Disponibilidad propia (autoservicio operador)');
    }

    public function test_checking_the_toggle_grants_the_three_permissions(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();

        $response = $this->actingAs($admin)->put(route('users.update', $user), array_merge(
            $this->baseFields($user),
            ['can_manage_own_availability' => '1']
        ));

        $response->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->hasPermissionTo('ver disponibilidad_propia'));
        $this->assertTrue($user->hasPermissionTo('crear disponibilidad_propia'));
        $this->assertTrue($user->hasPermissionTo('eliminar disponibilidad_propia'));
    }

    public function test_unchecking_the_toggle_revokes_the_three_permissions(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();
        $user->givePermissionTo(['ver disponibilidad_propia', 'crear disponibilidad_propia', 'eliminar disponibilidad_propia']);

        $response = $this->actingAs($admin)->put(route('users.update', $user), array_merge(
            $this->baseFields($user),
            ['can_manage_own_availability' => '0']
        ));

        $response->assertRedirect();
        $user->refresh();
        $this->assertFalse($user->hasPermissionTo('ver disponibilidad_propia'));
        $this->assertFalse($user->hasPermissionTo('crear disponibilidad_propia'));
        $this->assertFalse($user->hasPermissionTo('eliminar disponibilidad_propia'));
    }

    public function test_toggle_does_not_wipe_other_permissions_from_the_crud_matrix(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();

        $response = $this->actingAs($admin)->put(route('users.update', $user), array_merge(
            $this->baseFields($user),
            [
                'permissions' => ['ver agenda', 'crear agenda'],
                'can_manage_own_availability' => '1',
            ]
        ));

        $response->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->hasPermissionTo('ver agenda'));
        $this->assertTrue($user->hasPermissionTo('crear agenda'));
        $this->assertTrue($user->hasPermissionTo('crear disponibilidad_propia'));
    }

    public function test_new_user_can_be_created_with_the_toggle_checked(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'nuevooperador'.uniqid(),
            'email' => 'nuevo-operador-'.uniqid().'@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
            'is_operator' => false,
            'can_manage_own_availability' => '1',
        ]);

        $response->assertRedirect();
        $created = User::where('email', 'like', 'nuevo-operador-%')->firstOrFail();
        $this->assertTrue($created->hasPermissionTo('crear disponibilidad_propia'));
    }
}
