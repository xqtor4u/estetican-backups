<?php

namespace App\Support\Geo;

use App\Models\Branch;
use App\Models\Pet;
use App\Support\SystemSettings\SystemSettings;

class CoverageChecker
{
    public function __construct(private SystemSettings $settings) {}

    /**
     * @return array{distance_km: float, radius_km: int, branch_name: string}|null
     *                                                                             null si está dentro de cobertura, o si no hay datos suficientes para evaluar
     *                                                                             (mascota/cliente sin coordenadas, o ninguna sucursal con coordenadas cargadas).
     */
    public function checkPet(Pet $pet): ?array
    {
        [$lat, $lng] = $this->resolvePetCoordinates($pet);

        if ($lat === null || $lng === null) {
            return null;
        }

        $nearestBranch = $this->nearestBranch($lat, $lng);

        if (! $nearestBranch) {
            return null;
        }

        $radiusKm = (int) $this->settings->all()['coverage_radius_km'];
        $distanceKm = DistanceCalculator::kmBetween($lat, $lng, (float) $nearestBranch->lat, (float) $nearestBranch->lng);

        if ($distanceKm <= $radiusKm) {
            return null;
        }

        return [
            'distance_km' => round($distanceKm, 1),
            'radius_km' => $radiusKm,
            'branch_name' => $nearestBranch->name,
        ];
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function resolvePetCoordinates(Pet $pet): array
    {
        if ($pet->lat !== null && $pet->lng !== null) {
            return [(float) $pet->lat, (float) $pet->lng];
        }

        $pet->loadMissing('client.addresses');

        $address = $pet->client?->addresses
            ->first(fn ($address) => $address->lat !== null && $address->lng !== null);

        if (! $address) {
            return [null, null];
        }

        return [(float) $address->lat, (float) $address->lng];
    }

    private function nearestBranch(float $lat, float $lng): ?Branch
    {
        return Branch::query()
            ->where('is_active', true)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get()
            ->sortBy(fn (Branch $branch) => DistanceCalculator::kmBetween($lat, $lng, (float) $branch->lat, (float) $branch->lng))
            ->first();
    }
}
