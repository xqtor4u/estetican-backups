<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OperatorSelfServiceUnavailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(array $permissions = [], ?Operator $operator = null): array
    {
        $user = User::create([
            'name' => 'Operador Test',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test',
            'email' => 'operador-self-service-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
            'operator_id' => $operator?->id,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        if ($permissions) {
            $user->givePermissionTo($permissions);
        }

        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['user' => $user, 'headers' => ['Authorization' => "Bearer {$plainToken}"]];
    }

    private function operator(string $name = 'Jose'): Operator
    {
        return Operator::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    public function test_index_rejects_user_without_permission(): void
    {
        $operator = $this->operator();
        ['headers' => $headers] = $this->userWithToken([], $operator);

        $response = $this->withHeaders($headers)->getJson('/api/me/unavailabilities');

        $response->assertStatus(403);
    }

    public function test_index_rejects_user_without_linked_operator(): void
    {
        ['headers' => $headers] = $this->userWithToken(['ver disponibilidad_propia']);

        $response = $this->withHeaders($headers)->getJson('/api/me/unavailabilities');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Tu usuario no tiene un operador vinculado.']);
    }

    public function test_index_returns_only_own_unavailabilities(): void
    {
        $operator = $this->operator('Jose');
        $otherOperator = $this->operator('Maria');
        ['headers' => $headers] = $this->userWithToken(['ver disponibilidad_propia'], $operator);

        $operator->unavailabilities()->create(['starts_at' => '2026-08-01 00:00:00', 'ends_at' => '2026-08-05 00:00:00', 'reason' => 'Mias']);
        $otherOperator->unavailabilities()->create(['starts_at' => '2026-08-10 00:00:00', 'ends_at' => '2026-08-12 00:00:00', 'reason' => 'De otro']);

        $response = $this->withHeaders($headers)->getJson('/api/me/unavailabilities');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['reason' => 'Mias']);
        $response->assertJsonMissing(['reason' => 'De otro']);
    }

    public function test_store_rejects_user_without_permission(): void
    {
        $operator = $this->operator();
        ['headers' => $headers] = $this->userWithToken([], $operator);

        $response = $this->withHeaders($headers)->postJson('/api/me/unavailabilities', [
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-05 00:00:00',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('operator_unavailabilities', 0);
    }

    public function test_store_creates_unavailability_linked_to_own_operator_ignoring_operator_id_in_payload(): void
    {
        $operator = $this->operator('Jose');
        $otherOperator = $this->operator('Maria');
        ['headers' => $headers] = $this->userWithToken(['crear disponibilidad_propia'], $operator);

        $response = $this->withHeaders($headers)->postJson('/api/me/unavailabilities', [
            'operator_id' => $otherOperator->id,
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-05 00:00:00',
            'reason' => 'Vacaciones',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('operator_unavailabilities', [
            'operator_id' => $operator->id,
            'reason' => 'Vacaciones',
        ]);
        $this->assertDatabaseMissing('operator_unavailabilities', [
            'operator_id' => $otherOperator->id,
        ]);
    }

    public function test_destroy_removes_own_unavailability(): void
    {
        $operator = $this->operator();
        ['headers' => $headers] = $this->userWithToken(['eliminar disponibilidad_propia'], $operator);
        $unavailability = $operator->unavailabilities()->create(['starts_at' => '2026-08-01 00:00:00', 'ends_at' => '2026-08-05 00:00:00']);

        $response = $this->withHeaders($headers)->deleteJson("/api/me/unavailabilities/{$unavailability->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('operator_unavailabilities', ['id' => $unavailability->id]);
    }

    public function test_destroy_rejects_unavailability_of_another_operator_even_with_permission(): void
    {
        $operator = $this->operator('Jose');
        $otherOperator = $this->operator('Maria');
        ['headers' => $headers] = $this->userWithToken(['eliminar disponibilidad_propia'], $operator);
        $unavailability = $otherOperator->unavailabilities()->create(['starts_at' => '2026-08-01 00:00:00', 'ends_at' => '2026-08-05 00:00:00']);

        $response = $this->withHeaders($headers)->deleteJson("/api/me/unavailabilities/{$unavailability->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('operator_unavailabilities', ['id' => $unavailability->id]);
    }

    public function test_base_roles_seeder_creates_self_service_permissions(): void
    {
        $this->seed(\Database\Seeders\BaseRolesSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'ver disponibilidad_propia']);
        $this->assertDatabaseHas('permissions', ['name' => 'crear disponibilidad_propia']);
        $this->assertDatabaseHas('permissions', ['name' => 'editar disponibilidad_propia']);
        $this->assertDatabaseHas('permissions', ['name' => 'eliminar disponibilidad_propia']);
    }
}
