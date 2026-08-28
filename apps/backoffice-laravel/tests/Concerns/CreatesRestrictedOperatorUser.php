<?php

namespace Tests\Concerns;

use App\Models\ApiToken;
use App\Models\Operator;
use App\Models\User;
use Database\Seeders\BaseRolesSeeder;

/**
 * Usuario NO admin con permisos granulares elegidos a mano — para probar el scope de
 * "operador restringido" (solo su propia agenda, sin `ver clientes`/`ver mascotas`/
 * `ver operadores`). Complementa `CreatesAdminUser`, que siempre da acceso total.
 */
trait CreatesRestrictedOperatorUser
{
    protected function createOperatorUser(array $permissions = [], ?Operator $operator = null): User
    {
        (new BaseRolesSeeder)->run();

        $user = User::create([
            'name' => 'Operador Test '.uniqid(),
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test',
            'email' => 'operador-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
            'operator_id' => $operator?->id,
        ]);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    protected function operatorAuthHeader(User $user): array
    {
        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['Authorization' => "Bearer {$plainToken}"];
    }
}
