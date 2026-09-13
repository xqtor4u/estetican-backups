<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\OperatorRole;
use App\Models\OperatorRoleServiceTemplate;
use App\Models\OperatorServiceCapability;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-102 (Fase 3 de SYNC-073, §6.4): al quitarle un rol a un operador desde su ficha, los
 * servicios que **solo** ese rol le aportaba se "congelan" como capacidad directa (`grant`) por
 * default — para que no pierda de golpe algo que sí podía hacer. `freeze_removed_role_services=0`
 * explícito hace lo contrario (los deja perder).
 */
class RoleRemovalFreezeTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function updatePayload(Operator $operator, array $overrides = []): array
    {
        return array_merge([
            'code' => $operator->code,
            'first_name' => $operator->first_name,
            'is_active' => true,
        ], $overrides);
    }

    public function test_removing_a_role_freezes_the_service_it_uniquely_provided_by_default(): void
    {
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Consulta', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true]);
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $service->id]);

        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra', 'first_name' => 'Dra', 'is_active' => true]);
        $operator->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);

        $response = $this->actingAs($this->createAdminUser())
            ->put(route('operators.update', $operator), $this->updatePayload($operator, ['role_ids' => []]));

        $response->assertRedirect(route('operators.edit', $operator));
        $this->assertDatabaseHas('operator_service_capabilities', [
            'operator_id' => $operator->id,
            'service_id' => $service->id,
            'mode' => OperatorServiceCapability::MODE_GRANT,
        ]);
    }

    public function test_explicitly_choosing_not_to_freeze_lets_the_service_go(): void
    {
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Consulta', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true]);
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $service->id]);

        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra', 'first_name' => 'Dra', 'is_active' => true]);
        $operator->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);

        $this->actingAs($this->createAdminUser())->put(route('operators.update', $operator), $this->updatePayload($operator, [
            'role_ids' => [],
            'freeze_removed_role_services' => '0',
        ]));

        $this->assertDatabaseMissing('operator_service_capabilities', [
            'operator_id' => $operator->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_a_service_still_covered_by_a_remaining_role_is_not_frozen(): void
    {
        $roleA = OperatorRole::create(['code' => 'a'.uniqid(), 'name' => 'Rol A '.uniqid()]);
        $roleB = OperatorRole::create(['code' => 'b'.uniqid(), 'name' => 'Rol B '.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 200, 'duration_minutes' => 30, 'is_active' => true]);
        OperatorRoleServiceTemplate::create(['operator_role_id' => $roleA->id, 'service_id' => $service->id]);
        OperatorRoleServiceTemplate::create(['operator_role_id' => $roleB->id, 'service_id' => $service->id]);

        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Ana', 'first_name' => 'Ana', 'is_active' => true]);
        $operator->roles()->attach([$roleA->id, $roleB->id], ['starts_at' => now()]);

        // Quita A, conserva B — B ya cubre el mismo servicio, no hay nada que congelar.
        $this->actingAs($this->createAdminUser())->put(route('operators.update', $operator), $this->updatePayload($operator, [
            'role_ids' => [$roleB->id],
        ]));

        $this->assertDatabaseMissing('operator_service_capabilities', [
            'operator_id' => $operator->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_a_service_with_an_existing_capability_row_is_left_untouched(): void
    {
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Consulta', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true]);
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $service->id]);

        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra', 'first_name' => 'Dra', 'is_active' => true]);
        $operator->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);
        // Ya tenía una baja explícita de este servicio (revoke) desde antes.
        OperatorServiceCapability::create([
            'operator_id' => $operator->id,
            'service_id' => $service->id,
            'mode' => OperatorServiceCapability::MODE_REVOKE,
        ]);

        $this->actingAs($this->createAdminUser())->put(route('operators.update', $operator), $this->updatePayload($operator, [
            'role_ids' => [],
        ]));

        // Sigue como estaba: revoke, no se convirtió en grant ni se tocó.
        $this->assertDatabaseHas('operator_service_capabilities', [
            'operator_id' => $operator->id,
            'service_id' => $service->id,
            'mode' => OperatorServiceCapability::MODE_REVOKE,
        ]);
    }

    public function test_a_service_open_to_all_operators_is_never_frozen(): void
    {
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño Premium', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true, 'open_to_all_operators' => true]);
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $service->id]);

        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra', 'first_name' => 'Dra', 'is_active' => true]);
        $operator->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);

        $this->actingAs($this->createAdminUser())->put(route('operators.update', $operator), $this->updatePayload($operator, [
            'role_ids' => [],
        ]));

        $this->assertDatabaseMissing('operator_service_capabilities', [
            'operator_id' => $operator->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_not_removing_any_role_does_not_touch_capabilities(): void
    {
        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Consulta', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true]);
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $service->id]);

        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra', 'first_name' => 'Dra', 'is_active' => true]);
        $operator->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);

        $this->actingAs($this->createAdminUser())->put(route('operators.update', $operator), $this->updatePayload($operator, [
            'role_ids' => [$role->id],
        ]));

        $this->assertDatabaseCount('operator_service_capabilities', 0);
    }
}
