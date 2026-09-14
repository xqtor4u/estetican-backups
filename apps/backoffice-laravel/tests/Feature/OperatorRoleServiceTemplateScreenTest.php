<?php

namespace Tests\Feature;

use App\Models\OperatorRole;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-073 Fase 2 · §6.1 — editor de la plantilla de servicios de un rol de puesto,
 * dentro de la pantalla "Tipos de operador".
 */
class OperatorRoleServiceTemplateScreenTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function service(string $name, string $type = 'spa'): Service
    {
        return Service::create(['code' => 'SVC'.uniqid(), 'type' => $type, 'name' => $name, 'price' => 100, 'duration_minutes' => 30]);
    }

    public function test_edit_screen_lists_active_services_with_current_template_checked(): void
    {
        $role = OperatorRole::create(['code' => 'VET'.uniqid(), 'name' => 'Veterinario']);
        $inTemplate = $this->service('Vacuna rabia', 'vaccine');
        $notInTemplate = $this->service('Baño');
        $inactive = $this->service('Servicio viejo');
        $inactive->update(['is_active' => false]);
        $role->templatedServices()->attach($inTemplate->id);

        $response = $this->actingAs($this->createAdminUser())->get(route('operator-roles.edit', $role));

        $response->assertOk();
        $response->assertSee('Servicios de la plantilla');
        $response->assertSee('Vacuna rabia');
        $response->assertSee('Baño');
        $response->assertDontSee('Servicio viejo');
        $response->assertSee('name="template_service_ids[]"', false);
        $response->assertSee('id="tpl-svc-'.$inTemplate->id.'"', false);
    }

    public function test_updating_the_template_syncs_the_rows(): void
    {
        $role = OperatorRole::create(['code' => 'VET'.uniqid(), 'name' => 'Veterinario']);
        $a = $this->service('Vacuna rabia', 'vaccine');
        $b = $this->service('Vacuna quíntuple', 'vaccine');
        $c = $this->service('Baño');
        $role->templatedServices()->attach($c->id);

        $response = $this->actingAs($this->createAdminUser())
            ->put(route('operator-roles.template.update', $role), ['template_service_ids' => [$a->id, $b->id]]);

        $response->assertRedirect(route('operator-roles.edit', $role));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $role->templatedServices()->pluck('services.id')->all());
        $this->assertDatabaseMissing('operator_role_service_template', ['operator_role_id' => $role->id, 'service_id' => $c->id]);
    }

    public function test_submitting_no_services_clears_the_template(): void
    {
        $role = OperatorRole::create(['code' => 'VET'.uniqid(), 'name' => 'Veterinario']);
        $a = $this->service('Vacuna', 'vaccine');
        $role->templatedServices()->attach($a->id);

        $this->actingAs($this->createAdminUser())
            ->put(route('operator-roles.template.update', $role), [])
            ->assertRedirect();

        $this->assertSame(0, $role->templatedServices()->count());
    }

    public function test_updating_the_template_requires_editar_operadores(): void
    {
        $role = OperatorRole::create(['code' => 'VET'.uniqid(), 'name' => 'Veterinario']);

        $user = $this->createAdminUser();
        $user->syncRoles([]);
        $user->syncPermissions(['ver operadores']);

        $this->actingAs($user)
            ->put(route('operator-roles.template.update', $role), ['template_service_ids' => []])
            ->assertForbidden();
    }
}
