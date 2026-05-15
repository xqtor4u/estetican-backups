<?php

namespace App\Support\CatalogCache;

use App\Models\Pet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PetCatalogCache
{
    private const SPECIES_OPTIONS_KEY = 'backoffice.catalogs.pets.species-options';

    public static function speciesOptions(): Collection
    {
        $speciesOptions = Cache::remember(
            self::SPECIES_OPTIONS_KEY,
            now()->addSeconds((int) config('backoffice.cache.catalog_ttl_seconds', 1800)),
            static fn (): array => Pet::query()
                ->whereNotNull('species')
                ->where('species', '!=', '')
                ->distinct()
                ->orderBy('species')
                ->pluck('species')
                ->all(),
        );

        if (!is_array($speciesOptions)) {
            Cache::forget(self::SPECIES_OPTIONS_KEY);

            return self::speciesOptions();
        }

        return collect($speciesOptions);
    }

    public static function flushSpeciesOptions(): void
    {
        Cache::forget(self::SPECIES_OPTIONS_KEY);
    }
}