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
 * SYNC-073 Fase 2 · §6.2 — panel "Servicios que realiza" en la ficha del operador:
 * grant / revoke sobre la lista efectiva, alineado con la plantilla.
 */
class OperatorServiceCapabilitiesScreenTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function service(string $name, string $type = 'spa'): Service
    {
        return Service::create(['code' => 'SVC'.uniqid(), 'type' => $type, 'name' => $name, 'price' => 100, 'duration_minutes' => 30]);
    }

    private function operatorWithRole(?OperatorRole &$role = null): Operator
    {
        $role = OperatorRole::create(['code' => 'ROL'.uniqid(), 'name' => 'Rol X']);
        $op = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Ana', 'first_name' => 'Ana', 'apellido_paterno' => 'Ruiz', 'is_active' => true]);
        $op->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);

        return $op;
    }

    public function test_edit_screen_shows_the_effective_service_list_with_origin(): void
    {
        $op = $this->operatorWithRole($role);
        $fromTemplate = $this->service('Vacuna', 'vaccine');
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $fromTemplate->id]);
        $direct = $this->service('Baño premium');
        OperatorServiceCapability::create(['operator_id' => $op->id, 'service_id' => $direct->id, 'mode' => 'grant']);
        $this->service('Corte'); // ni plantilla ni grant

        $response = $this->actingAs($this->createAdminUser())->get(route('operators.edit', $op));

        $response->assertOk();
        $response->assertSee('Servicios que realiza');
        $response->assertSee('Vacuna');
        $response->assertSee('Plantilla de rol');
        $response->assertSee('Baño premium');
        $response->assertSee('Capacidad directa');
        $response->assertSee('id="op-svc-'.$fromTemplate->id.'"', false);
    }

    public function test_unchecking_a_template_service_creates_a_revoke(): void
    {
        $op = $this->operatorWithRole($role);
        $vacuna = $this->service('Vacuna', 'vaccine');
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $vacuna->id]);

        // Envío sin ese servicio marcado.
        $this->actingAs($this->createAdminUser())
            ->put(route('operators.service-capabilities.update', $op), ['service_ids' => []])
            ->assertRedirect(route('operators.edit', $op));

        $this->assertDatabaseHas('operator_service_capabilities', [
            'operator_id' => $op->id, 'service_id' => $vacuna->id, 'mode' => 'revoke',
        ]);
    }

    public function test_checking_a_non_template_service_creates_a_grant(): void
    {
        $op = $this->operatorWithRole($role);
        $bath = $this->service('Baño');

        $this->actingAs($this->createAdminUser())
            ->put(route('operators.service-capabilities.update', $op), ['service_ids' => [$bath->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('operator_service_capabilities', [
            'operator_id' => $op->id, 'service_id' => $bath->id, 'mode' => 'grant',
        ]);
    }

    public function test_realigning_with_the_template_removes_the_capability_row(): void
    {
        $op = $this->operatorWithRole($role);
        $vacuna = $this->service('Vacuna', 'vaccine');
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $vacuna->id]);
        OperatorServiceCapability::create(['operator_id' => $op->id, 'service_id' => $vacuna->id, 'mode' => 'revoke']);

        // Vuelve a marcarlo → coincide con la plantilla → se borra la fila.
        $this->actingAs($this->createAdminUser())
            ->put(route('operators.service-capabilities.update', $op), ['service_ids' => [$vacuna->id]]);

        $this->assertDatabaseMissing('operator_service_capabilities', [
            'operator_id' => $op->id, 'service_id' => $vacuna->id,
        ]);
    }

    public function test_open_to_all_services_are_left_untouched(): void
    {
        $op = $this->operatorWithRole($role);
        $svc = $this->service('Consulta general');
        $svc->update(['open_to_all_operators' => true]);

        $this->actingAs($this->createAdminUser())
            ->put(route('operators.service-capabilities.update', $op), ['service_ids' => []]);

        $this->assertDatabaseCount('operator_service_capabilities', 0);
    }

    public function test_syncing_roles_does_not_touch_direct_capabilities(): void
    {
        $op = $this->operatorWithRole($role);
        $bath = $this->service('Baño');
        OperatorServiceCapability::create(['operator_id' => $op->id, 'service_id' => $bath->id, 'mode' => 'grant']);
        $otherRole = OperatorRole::create(['code' => 'ROL'.uniqid(), 'name' => 'Otro']);

        $this->actingAs($this->createAdminUser())
            ->put(route('operators.update', $op), [
                'first_name' => 'Ana', 'apellido_paterno' => 'Ruiz',
                'role_ids' => [$otherRole->id],
            ]);

        $this->assertDatabaseHas('operator_service_capabilities', [
            'operator_id' => $op->id, 'service_id' => $bath->id, 'mode' => 'grant',
        ]);
    }

    public function test_route_requires_editar_operadores(): void
    {
        $op = $this->operatorWithRole($role);
        $user = $this->createAdminUser();
        $user->syncRoles([]);
        $user->syncPermissions(['ver operadores']);

        $this->actingAs($user)
            ->put(route('operators.service-capabilities.update', $op), ['service_ids' => []])
            ->assertForbidden();
    }
}
