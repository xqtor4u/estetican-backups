<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\OperatorRole;
use App\Models\OperatorServiceCapability;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-073 Fase 2 · §6.3 — panel "Quién lo realiza" en el catálogo de servicios:
 * toggle abierto-a-todos + capacidades directas por operador.
 */
class ServiceWhoPerformsScreenTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function service(string $name = 'Baño'): Service
    {
        return Service::create(['code' => 'SVC'.uniqid(), 'type' => 'spa', 'name' => $name, 'price' => 100, 'duration_minutes' => 30]);
    }

    private function operator(string $name): Operator
    {
        return Operator::create(['code' => 'OP'.uniqid(), 'name' => $name, 'first_name' => $name, 'apellido_paterno' => 'X', 'is_active' => true]);
    }

    public function test_edit_screen_shows_eligible_operators_with_origin(): void
    {
        $service = $this->service();
        $role = OperatorRole::create(['code' => 'GRO'.uniqid(), 'name' => 'Groomer']);
        $byTemplate = $this->operator('Por plantilla');
        $byTemplate->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);
        $service->roleTemplates()->create(['operator_role_id' => $role->id]);
        $byGrant = $this->operator('Por grant');
        OperatorServiceCapability::create(['operator_id' => $byGrant->id, 'service_id' => $service->id, 'mode' => 'grant']);
        $this->operator('Nadie');

        $response = $this->actingAs($this->createAdminUser())->get(route('services.edit', $service));

        $response->assertOk();
        $response->assertSee('Quién lo realiza');
        $response->assertSee('Por plantilla');
        $response->assertSee('Plantilla de rol');
        $response->assertSee('Por grant');
        $response->assertSee('Capacidad directa');
        $response->assertDontSee('<td>Nadie</td>', false);
    }

    public function test_toggle_open_to_all_operators(): void
    {
        $service = $this->service();

        $this->actingAs($this->createAdminUser())
            ->put(route('services.eligibility.update', $service), ['open_to_all_operators' => '1'])
            ->assertRedirect(route('services.edit', $service));
        $this->assertTrue($service->fresh()->open_to_all_operators);

        $this->actingAs($this->createAdminUser())
            ->put(route('services.eligibility.update', $service), ['open_to_all_operators' => '0']);
        $this->assertFalse($service->fresh()->open_to_all_operators);
    }

    public function test_add_and_remove_a_direct_operator_capability(): void
    {
        $service = $this->service();
        $op = $this->operator('Nuevo');
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->post(route('services.operator-capabilities.store', $service), ['operator_id' => $op->id])
            ->assertRedirect(route('services.edit', $service));
        $this->assertDatabaseHas('operator_service_capabilities', [
            'operator_id' => $op->id, 'service_id' => $service->id, 'mode' => 'grant', 'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('services.operator-capabilities.destroy', [$service, $op]))
            ->assertRedirect(route('services.edit', $service));
        $this->assertDatabaseMissing('operator_service_capabilities', ['operator_id' => $op->id, 'service_id' => $service->id]);
    }

    public function test_capability_routes_require_editar_catalogo_servicios(): void
    {
        $service = $this->service();
        $op = $this->operator('X');
        $user = $this->createAdminUser();
        $user->syncRoles([]);
        $user->syncPermissions(['ver catalogo_servicios']);

        $this->actingAs($user)->put(route('services.eligibility.update', $service), ['open_to_all_operators' => '1'])->assertForbidden();
        $this->actingAs($user)->post(route('services.operator-capabilities.store', $service), ['operator_id' => $op->id])->assertForbidden();
    }

    /**
     * SYNC-103 (Fase 3 de SYNC-073, §8): `services.operator_role_id` se eliminó por completo —
     * crear/editar un servicio sin mandar ese campo debe funcionar exactamente igual que antes.
     */
    public function test_service_can_be_created_and_updated_without_operator_role_id(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)->post(route('services.store'), [
            'type' => 'spa',
            'name' => 'Corte de pelo',
            'suggested_price' => '250.00',
            'suggested_duration_minutes' => '45',
        ])->assertRedirect();

        $service = Service::where('name', 'Corte de pelo')->firstOrFail();
        $this->assertArrayNotHasKey('operator_role_id', $service->getAttributes());

        $this->actingAs($admin)->put(route('services.update', $service), [
            'type' => 'spa',
            'name' => 'Corte de pelo premium',
            'suggested_price' => '300.00',
            'suggested_duration_minutes' => '60',
        ])->assertRedirect();

        $this->assertSame('Corte de pelo premium', $service->fresh()->name);

        $this->actingAs($admin)->get(route('services.index'))->assertOk()->assertDontSee('Tipo de operador');
        $this->actingAs($admin)->get(route('services.edit', $service))->assertOk()->assertDontSee('Tipo de operador');
    }
}
