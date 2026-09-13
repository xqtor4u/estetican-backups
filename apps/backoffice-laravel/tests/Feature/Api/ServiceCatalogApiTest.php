<?php

namespace Tests\Feature\Api;

use App\Models\Operator;
use App\Models\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-043: el agendado móvil cruza los operadores ofrecidos por línea con los roles
 * activos del operador. `/api/operators` tiene que exponer los campos que ese cruce
 * necesita. (El test de `/api/services` sobre `operator_role_id` se retiró en
 * SYNC-103 junto con la columna — la elegibilidad real ya no pasa por ahí, ver
 * `OperatorServiceResolver::canPerform()`.)
 */
class ServiceCatalogApiTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

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
