<?php

namespace App\Domain\Planning\Services;

use App\Models\OperatorRole;
use App\Models\OperatorRoleAssignment;
use App\Models\OperatorRoleServiceTemplate;
use App\Models\OperatorServiceCapability;
use App\Models\Service;
use App\Support\CatalogCache\OperatorServiceCapabilityCache;
use Illuminate\Support\Facades\DB;

/**
 * Backfill idempotente de SYNC-073: lleva el estado actual (`services.operator_role_id` +
 * roles interinos `CAP-BANO`/`CAP-CORTE`) al modelo nuevo (plantilla por rol de puesto +
 * capacidades directas por operador), SIN cambiar quién puede hacer qué.
 *
 * Lo llama la migración `2026_08_30_000001`. Se deja como clase aparte para poder re-correrlo
 * y testearlo.
 *
 * Ver docs/tecnico/SPEC_CAPACIDADES_SERVICIO_POR_OPERADOR.md (Zeus-Estetican, §4).
 */
class OperatorServiceBackfill
{
    /** Roles interinos de capacidad que esta feature retira (§0.4). */
    public const RETIRED_ROLE_CODES = ['CAP-BANO', 'CAP-CORTE'];

    public function run(): void
    {
        DB::transaction(function (): void {
            // Orden importante:
            //  1) las vacunas (type = 'vaccine') SIEMPRE van a la plantilla del rol Veterinario
            //     (§0.3), aunque hoy tengan operator_role_id null — no son "abiertas a todos".
            //  2) "abrir los sin rol" mira el estado ORIGINAL de operator_role_id (y ya excluye
            //     las vacunas); si corriera después reabriría los baños/cortes que el retiro
            //     acaba de acotar.
            $this->templateVaccinesUnderVeterinario();
            $this->openServicesThatHadNoRole();
            $this->seedRoleTemplatesFromServiceRole();
            $this->retireCapabilityRoles();
        });

        OperatorServiceCapabilityCache::flush();
    }

    /**
     * Paso 0 (§0.3) — una vacuna la aplica un veterinario, no "cualquiera". Todos los servicios
     * `type = 'vaccine'` entran a la plantilla del rol `veterinario` (exista o no el módulo
     * clínico) y quedan `open_to_all_operators = false`. Si no hay rol `veterinario` en la BD,
     * no se toca nada.
     */
    private function templateVaccinesUnderVeterinario(): void
    {
        $vetRoleId = OperatorRole::query()->where('code', 'veterinario')->value('id');

        if (! $vetRoleId) {
            return;
        }

        Service::query()
            ->where('type', 'vaccine')
            ->get(['id', 'operator_role_id'])
            ->each(function (Service $service) use ($vetRoleId): void {
                OperatorRoleServiceTemplate::firstOrCreate([
                    'operator_role_id' => $vetRoleId,
                    'service_id' => $service->id,
                ]);
                $service->forceFill([
                    'operator_role_id' => null,
                    'open_to_all_operators' => false,
                ])->save();
            });
    }

    /**
     * Paso 1 — cada servicio que hoy exige un rol de PUESTO (no interino) pasa ese vínculo a la
     * plantilla de ese rol.
     */
    private function seedRoleTemplatesFromServiceRole(): void
    {
        $retiredIds = OperatorRole::query()
            ->whereIn('code', self::RETIRED_ROLE_CODES)
            ->pluck('id');

        Service::query()
            ->whereNotNull('operator_role_id')
            ->whereNotIn('operator_role_id', $retiredIds)
            ->get(['id', 'operator_role_id'])
            ->each(function (Service $service): void {
                OperatorRoleServiceTemplate::firstOrCreate([
                    'operator_role_id' => $service->operator_role_id,
                    'service_id' => $service->id,
                ]);
            });
    }

    /**
     * Paso 2 — retiro de `CAP-BANO` / `CAP-CORTE`: cada (servicio que los exige) × (operador que
     * los tiene) se convierte en una capacidad directa `grant`; luego se limpia el
     * `operator_role_id` de esos servicios y se borran el rol y sus asignaciones.
     */
    private function retireCapabilityRoles(): void
    {
        $roles = OperatorRole::query()
            ->whereIn('code', self::RETIRED_ROLE_CODES)
            ->get();

        foreach ($roles as $role) {
            $serviceIds = Service::query()
                ->where('operator_role_id', $role->id)
                ->pluck('id');

            $operatorIds = OperatorRoleAssignment::query()
                ->where('operator_role_id', $role->id)
                ->whereNull('ends_at')
                ->pluck('operator_id')
                ->unique();

            foreach ($serviceIds as $serviceId) {
                foreach ($operatorIds as $operatorId) {
                    OperatorServiceCapability::firstOrCreate(
                        ['operator_id' => $operatorId, 'service_id' => $serviceId],
                        [
                            'mode' => OperatorServiceCapability::MODE_GRANT,
                            'note' => "backfill retiro {$role->code} (SYNC-073)",
                            'created_by_user_id' => null,
                        ],
                    );
                }
            }

            Service::query()->where('operator_role_id', $role->id)->update(['operator_role_id' => null]);
            OperatorRoleAssignment::query()->where('operator_role_id', $role->id)->delete();
            $role->delete();
        }
    }

    /**
     * Paso 3 — «sin cambiar quién puede hacer qué» (§4). Hoy un servicio con
     * `operator_role_id = null` lo puede agendar cualquier operador (el guard hace `continue`).
     * Bajo el modelo nuevo, "sin plantilla, sin capacidad, sin toggle" = nadie. Para conservar
     * la semántica, esos servicios quedan `open_to_all_operators = true` (el admin los acota
     * después, patrón §0.2).
     *
     * Excluye las vacunas: esas van a la plantilla de Veterinario (paso 0), no abiertas a todos.
     *
     * Verificado antes de portar (12/09/2026): 0 de los 8 servicios `spa` de esta BD tienen
     * `operator_role_id` nulo — este paso es no-operativo aquí.
     */
    private function openServicesThatHadNoRole(): void
    {
        Service::query()
            ->whereNull('operator_role_id')
            ->where('type', '!=', 'vaccine')
            ->where('open_to_all_operators', false)
            ->update(['open_to_all_operators' => true]);
    }
}
