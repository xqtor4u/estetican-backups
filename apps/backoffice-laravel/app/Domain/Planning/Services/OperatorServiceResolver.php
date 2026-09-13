<?php

namespace App\Domain\Planning\Services;

use App\Models\Operator;
use App\Models\OperatorRoleServiceTemplate;
use App\Models\OperatorServiceCapability;
use App\Models\Service;
use App\Support\CatalogCache\OperatorServiceCapabilityCache;
use Illuminate\Support\Collection;

/**
 * Resuelve la relación m2m operador ↔ servicio sin materializarla (SYNC-073).
 *
 * Capacidades efectivas de un operador =
 *   ( ⋃ plantillas de sus roles activos )  ∪  { altas directas grant }  −  { bajas directas revoke }
 *
 * y aparte, si el servicio tiene `open_to_all_operators = true`, cualquier operador es elegible
 * y se ignora todo lo anterior.
 *
 * Ver docs/tecnico/SPEC_CAPACIDADES_SERVICIO_POR_OPERADOR.md (Zeus-Estetican, §2, §3.5).
 */
class OperatorServiceResolver
{
    public function canPerform(Operator $operator, Service $service): bool
    {
        if ($service->open_to_all_operators) {
            return true;
        }

        $cap = OperatorServiceCapability::query()
            ->where('operator_id', $operator->id)
            ->where('service_id', $service->id)
            ->first();

        if ($cap) {
            return $cap->mode === OperatorServiceCapability::MODE_GRANT;
        }

        $roleIds = $operator->activeRoles()->pluck('id');

        if ($roleIds->isEmpty()) {
            return false;
        }

        return OperatorRoleServiceTemplate::query()
            ->where('service_id', $service->id)
            ->whereIn('operator_role_id', $roleIds)
            ->exists();
    }

    /**
     * Servicios (activos) que un operador puede realizar. Cacheado por operador.
     *
     * @return Collection<int, Service>
     */
    public function servicesFor(Operator $operator): Collection
    {
        $ids = OperatorServiceCapabilityCache::rememberForOperator(
            $operator->id,
            fn (): array => $this->resolveServiceIdsFor($operator),
        );

        if ($ids === []) {
            return collect();
        }

        return Service::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Operadores (activos) que pueden realizar un servicio. Para el catálogo de servicios y el
     * filtro del agendado.
     *
     * @return Collection<int, Operator>
     */
    public function operatorsFor(Service $service): Collection
    {
        $operators = Operator::query()
            ->where('is_active', true)
            ->with('roles')
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('first_name')
            ->get();

        if ($service->open_to_all_operators) {
            return $operators;
        }

        return $operators->filter(fn (Operator $o) => $this->canPerform($o, $service))->values();
    }

    /**
     * De dónde le viene a un operador la elegibilidad para un servicio:
     * `open_to_all` | `grant` (capacidad directa) | `template` (plantilla de un rol activo) | `none`.
     */
    public function originFor(Operator $operator, Service $service): string
    {
        if ($service->open_to_all_operators) {
            return 'open_to_all';
        }

        $cap = OperatorServiceCapability::query()
            ->where('operator_id', $operator->id)
            ->where('service_id', $service->id)
            ->first();

        if ($cap) {
            return $cap->mode === OperatorServiceCapability::MODE_GRANT ? 'grant' : 'none';
        }

        $roleIds = $operator->activeRoles()->pluck('id');

        $inTemplate = $roleIds->isNotEmpty() && OperatorRoleServiceTemplate::query()
            ->where('service_id', $service->id)
            ->whereIn('operator_role_id', $roleIds)
            ->exists();

        return $inTemplate ? 'template' : 'none';
    }

    /**
     * Set de `service_id` que el operador puede hacer, sin el filtro `is_active` (ese lo aplica
     * `servicesFor`). No mira `open_to_all_operators` — ese caso lo cubre cada consulta puntual.
     *
     * @return array<int>
     */
    private function resolveServiceIdsFor(Operator $operator): array
    {
        $roleIds = $operator->activeRoles()->pluck('id');

        $fromTemplate = $roleIds->isEmpty()
            ? collect()
            : OperatorRoleServiceTemplate::query()
                ->whereIn('operator_role_id', $roleIds)
                ->pluck('service_id');

        $caps = OperatorServiceCapability::query()
            ->where('operator_id', $operator->id)
            ->get(['service_id', 'mode']);

        $granted = $caps->where('mode', OperatorServiceCapability::MODE_GRANT)->pluck('service_id');
        $revoked = $caps->where('mode', OperatorServiceCapability::MODE_REVOKE)->pluck('service_id');

        return $fromTemplate
            ->merge($granted)
            ->unique()
            ->reject(fn ($id) => $revoked->contains($id))
            ->values()
            ->all();
    }
}
