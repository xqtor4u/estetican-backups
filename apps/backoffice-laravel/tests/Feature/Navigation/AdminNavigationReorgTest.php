<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use App\Support\Navigation\MainNavigation;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * A pedido del usuario (03/08/2026): RH salió de "Administración" a su propia pestaña
 * de nivel superior; lo que quedó (Inventario/Finanzas/Veterinaria) se renombró a
 * "Operaciones del negocio"; "Reportes" es pestaña nueva, con Bitácora de actividad
 * movida ahí desde Catálogos (encaja mejor temáticamente).
 */
class AdminNavigationReorgTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function superAdmin(): User
    {
        return $this->createAdminUser(['is_super_admin' => true]);
    }

    private function groupLabels(): \Illuminate\Support\Collection
    {
        return collect(MainNavigation::groups())->pluck('label');
    }

    private function groupByLabel(string $label): ?array
    {
        return collect(MainNavigation::groups())->firstWhere('label', $label);
    }

    public function test_rh_is_now_a_top_level_group_not_nested_under_administracion(): void
    {
        $this->actingAs($this->superAdmin());

        $this->assertTrue($this->groupLabels()->contains('RH'));
        $this->assertFalse($this->groupLabels()->contains('Administración'));

        $rh = $this->groupByLabel('RH');
        $this->assertArrayNotHasKey('subgroups', $rh);
        $this->assertTrue(collect($rh['items'])->pluck('label')->contains('Usuarios'));
    }

    public function test_operaciones_del_negocio_groups_inventario_finanzas_veterinaria_without_rh(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        $this->actingAs($this->superAdmin());

        $group = $this->groupByLabel('Operaciones del negocio');
        $this->assertNotNull($group);

        $subgroupLabels = collect($group['subgroups'])->pluck('label');
        $this->assertTrue($subgroupLabels->contains('Inventario'));
        $this->assertTrue($subgroupLabels->contains('Finanzas'));
        $this->assertTrue($subgroupLabels->contains('Veterinaria'));
        $this->assertFalse($subgroupLabels->contains('RH'));
    }

    public function test_reportes_is_a_new_top_level_group_with_activity_log(): void
    {
        $user = $this->superAdmin();
        Permission::firstOrCreate(['name' => 'ver usuarios', 'guard_name' => 'web']);
        $user->givePermissionTo('ver usuarios');
        $this->actingAs($user);

        $reportes = $this->groupByLabel('Reportes');
        $this->assertNotNull($reportes);

        $general = collect($reportes['subgroups'])->firstWhere('label', 'General');
        $this->assertNotNull($general);
        $this->assertTrue(collect($general['items'])->pluck('label')->contains('Bitácora de actividad'));

        $catalogos = $this->groupByLabel('Catálogos');
        $this->assertFalse(collect($catalogos['items'] ?? [])->pluck('label')->contains('Bitácora de actividad'));
    }

    /**
     * 16/08/2026: "Reportes" pasó de grupo plano a subgrupos (mismo patrón que "Operaciones del
     * negocio") para poder sumar los 5 reportes de Caja sin mezclarlos con Bitácora de actividad
     * — a pedido del usuario, aplicando el mismo principio de "un solo lugar por subcategorías".
     */
    public function test_reportes_has_a_caja_subgroup_with_the_five_cash_reports(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);

        $reportes = $this->groupByLabel('Reportes');
        $this->assertNotNull($reportes);

        $caja = collect($reportes['subgroups'])->firstWhere('label', 'Caja');
        $this->assertNotNull($caja);

        $labels = collect($caja['items'])->pluck('label');
        $this->assertTrue($labels->contains('Resumen de caja'));
        $this->assertTrue($labels->contains('Métodos de pago'));
        $this->assertTrue($labels->contains('Por operador'));
        $this->assertTrue($labels->contains('Pendientes por cobrar'));
        $this->assertTrue($labels->contains('Cierre de turno'));
    }
}
