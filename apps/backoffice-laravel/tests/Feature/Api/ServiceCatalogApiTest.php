<?php

namespace Tests\Feature\Api;

use App\Models\Operator;
use App\Models\OperatorRole;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-043: el agendado móvil filtra los operadores ofrecidos por línea con
 * Service.operator_role_id vs. los roles activos del operador. Estos dos endpoints
 * tienen que exponer los campos que ese cruce necesita.
 */
class ServiceCatalogApiTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    public function test_services_index_exposes_the_required_operator_role_id(): void
    {
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        $withRole = Service::create([
            'code' => 'SVC'.uniqid(), 'type' => 'spa', 'name' => 'Consulta', 'price' => 100,
            'duration_minutes' => 30, 'operator_role_id' => $role->id, 'is_active' => true,
        ]);
        $withoutRole = Service::create([
            'code' => 'SVC'.uniqid(), 'type' => 'spa', 'name' => 'Baño', 'price' => 100,
            'duration_minutes' => 30, 'is_active' => true,
        ]);

        $response = $this->withHeaders($this->createAdminAuthHeader())->getJson('/api/services');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $withRole->id, 'operator_role_id' => $role->id]);
        $response->assertJsonFragment(['id' => $withoutRole->id, 'operator_role_id' => null]);
    }

    public function test_operators_index_exposes_active_role_ids_only(): void
    {
        $active = OperatorRole::create(['code' => 'a'.uniqid(), 'name' => 'Estilista '.uniqid()]);
        $ended = OperatorRole::create(['code' => 'b'.uniqid(), 'name' => 'Baňista '.uniqid()]);

        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dani', 'first_name' => 'Dani', 'is_active' => true]);
        $operator->roles()->attach($active->id, ['is_primary' => true, 'starts_at' => now()]);
        $operator->roles()->attach($ended->id, ['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);

        $response = $this->withHeaders($this->createAdminAuthHeader())->getJson('/api/operators');

        $response->assertOk();
        $row = collect($response->json())->firstWhere('id', $operator->id);
        $this->assertNotNull($row);
        $this->assertEqualsCanonicalizing([$active->id], $row['role_ids']);
    }
}
