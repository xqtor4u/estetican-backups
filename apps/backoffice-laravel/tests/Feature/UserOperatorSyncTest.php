<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\OperatorRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Regresión: guardar en USEEDI un usuario ya vinculado a un operador tiraba 500
 * (`Unknown column 'operator_role_id'` en `operators`) porque `syncOperatorRecord()`
 * seguía escribiendo esa columna después de que la migración
 * 2026_07_31_000000_consolidate_operator_role_fields la eliminó.
 */
class UserOperatorSyncTest extends TestCase
{
    use CreatesAdminUser, RefreshDatabase;

    public function test_operators_table_no_longer_has_operator_role_id(): void
    {
        $this->assertFalse(
            Schema::hasColumn('operators', 'operator_role_id'),
            'La migración de consolidación debió quitar operators.operator_role_id.'
        );
    }

    public function test_saving_an_operator_linked_user_does_not_500(): void
    {
        $admin = $this->createAdminUser();
        $role = OperatorRole::create(['code' => 'GRO', 'name' => 'Groomer', 'is_active' => true]);
        $operator = Operator::create(['code' => 'GRO-01', 'name' => 'Peluquero Uno', 'is_active' => true]);

        $user = User::create([
            'name' => 'Operador Uno',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Uno',
            'email' => 'op-uno-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
            'is_operator' => true,
            'operator_id' => $operator->id,
            'operator_code' => 'GRO-01',
        ]);

        $response = $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
            'is_operator' => true,
            'operator_code' => 'GRO-01',
            'operator_role_id' => $role->id,
            'phone' => '5551234567',
        ]);

        $response->assertRedirect();

        // El "Tipo de Operador" se guarda en users.operator_role_id (esa columna sí existe).
        $this->assertSame($role->id, $user->refresh()->operator_role_id);
        // Y el registro de operador vinculado se actualizó sin tocar la columna eliminada.
        $this->assertSame('5551234567', $operator->refresh()->phone);
    }
}
