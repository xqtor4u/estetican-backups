<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SYNC-024 — Caja pasó a depender de `users.branch_id` (sucursal asignada, dato de RH
 * persistente) en vez del check-in del día, más permisos granulares de Caja. Antes de esto no
 * existía ninguna forma de asignar ni la sucursal ni esos permisos desde el backoffice — este
 * test cubre que la pantalla de usuario (USEEDI/USECRE) ahora sí lo permite.
 */
class UserBranchAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['caja.ver', 'caja.abrir', 'caja.cerrar', 'caja.movimientos.crear', 'caja.movimientos.editar', 'caja.movimientos.revertir', 'ver agenda', 'crear disponibilidad_propia'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-branch-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        return $admin;
    }

    private function targetUser(): User
    {
        return User::create([
            'name' => 'Operador Test',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test',
            'email' => 'operador-branch-test-'.uniqid().'@example.com',
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

    public function test_edit_page_shows_the_branch_select_with_real_branches(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Sucursal Norte']);

        $response = $this->actingAs($admin)->get(route('users.edit', $user));

        $response->assertOk();
        $response->assertSee('Sucursal asignada');
        $response->assertSee('Sucursal Norte');
    }

    public function test_assigning_a_branch_persists_it(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Sucursal Norte']);

        $response = $this->actingAs($admin)->put(route('users.update', $user), array_merge(
            $this->baseFields($user),
            ['branch_id' => $branch->id]
        ));

        $response->assertRedirect();
        $user->refresh();
        $this->assertSame($branch->id, $user->branch_id);
    }

    public function test_unassigning_a_branch_clears_it(): void
    {
        $admin = $this->admin();
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Sucursal Norte']);
        $user = $this->targetUser();
        $user->update(['branch_id' => $branch->id]);

        $response = $this->actingAs($admin)->put(route('users.update', $user), array_merge(
            $this->baseFields($user),
            ['branch_id' => '']
        ));

        $response->assertRedirect();
        $user->refresh();
        $this->assertNull($user->branch_id);
    }

    public function test_rejects_a_branch_id_that_does_not_exist(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();

        $response = $this->actingAs($admin)->put(route('users.update', $user), array_merge(
            $this->baseFields($user),
            ['branch_id' => 99999]
        ));

        $response->assertSessionHasErrors('branch_id');
        $this->assertNull($user->fresh()->branch_id);
    }

    public function test_granting_caja_permissions_from_the_checkboxes_works(): void
    {
        $admin = $this->admin();
        $user = $this->targetUser();

        $response = $this->actingAs($admin)->put(route('users.update', $user), array_merge(
            $this->baseFields($user),
            ['permissions' => ['caja.ver', 'caja.movimientos.crear']]
        ));

        $response->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->hasPermissionTo('caja.ver'));
        $this->assertTrue($user->hasPermissionTo('caja.movimientos.crear'));
        $this->assertFalse($user->hasPermissionTo('caja.movimientos.revertir'));
    }

    public function test_new_user_can_be_created_with_a_branch_and_caja_permissions(): void
    {
        $admin = $this->admin();
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Sucursal Norte']);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'nuevooperador'.uniqid(),
            'email' => 'nuevo-operador-branch-'.uniqid().'@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
            'is_operator' => false,
            'branch_id' => $branch->id,
            'permissions' => ['caja.ver', 'caja.abrir'],
        ]);

        $response->assertRedirect();
        $created = User::where('email', 'like', 'nuevo-operador-branch-%')->firstOrFail();
        $this->assertSame($branch->id, $created->branch_id);
        $this->assertTrue($created->hasPermissionTo('caja.ver'));
        $this->assertTrue($created->hasPermissionTo('caja.abrir'));
    }
}
