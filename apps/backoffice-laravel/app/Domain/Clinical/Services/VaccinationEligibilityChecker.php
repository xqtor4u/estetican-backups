<?php

namespace App\Domain\Clinical\Services;

use App\Models\Pet;
use App\Models\Service;
use App\Support\SystemSettings\SystemSettings;

class VaccinationEligibilityChecker
{
    public function __construct(private SystemSettings $settings) {}

    /**
     * @return array{missing_vaccines: array<int, string>}|null
     *                                                            null si todas las vacunas "core" (services.type='vaccine', is_core_vaccine=true)
     *                                                            están vigentes (o no hay ninguna dada de alta).
     *                                                            Nunca bloquea — solo informa qué falta para que el staff decida (mismo espíritu que CoverageChecker).
     */
    public function check(Pet $pet): ?array
    {
        // El chequeo entero queda inerte mientras el módulo clínico esté apagado —
        // no debe advertir sobre vacunas de una funcionalidad que el usuario aún no activó.
        if (! $this->settings->all()['clinical_module_enabled']) {
            return null;
        }

        $coreVaccines = Service::where('type', 'vaccine')
            ->where('is_core_vaccine', true)
            ->where('is_active', true)
            ->get(['id', 'name']);

        if ($coreVaccines->isEmpty()) {
            return null;
        }

        $pet->loadMissing('vaccinations');

        $missing = $coreVaccines
            ->reject(function (Service $vaccine) use ($pet) {
                return $pet->vaccinations
                    ->where('service_id', $vaccine->id)
                    ->contains(fn ($vaccination) => $vaccination->expires_at !== null && $vaccination->expires_at->isFuture());
            })
            ->pluck('name')
            ->values()
            ->all();

        if ($missing === []) {
            return null;
        }

        return ['missing_vaccines' => $missing];
    }
}
