<?php

namespace Tests\Concerns;

use App\Models\ApiToken;
use App\Models\User;
use Database\Seeders\BaseRolesSeeder;

trait CreatesAdminUser
{
    protected function createAdminUser(array $overrides = []): User
    {
        // El rol/permisos 'admin' no vienen sembrados en la BD de testing por
        // default (RefreshDatabase solo corre migraciones) — BaseRolesSeeder es
        // idempotente (firstOrCreate/syncPermissions), seguro de correr por test.
        (new BaseRolesSeeder())->run();

        $user = User::create(array_merge([
            'name' => 'Admin Test '.uniqid(),
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ], $overrides));

        $user->assignRole('admin');

        return $user;
    }

    protected function createAdminAuthHeader(?User $user = null): array
    {
        $user ??= $this->createAdminUser();

        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['Authorization' => "Bearer {$plainToken}"];
    }
}
