<?php

namespace App\Support\CatalogCache;

use App\Models\OperatorRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class OperatorRoleCatalogCache
{
    private const ACTIVE_FORMS_KEY = 'backoffice.catalogs.operator-roles.active-for-forms';

    public static function activeForForms(): Collection
    {
        $roleRows = Cache::remember(
            self::ACTIVE_FORMS_KEY,
            now()->addSeconds((int) config('backoffice.cache.catalog_ttl_seconds', 1800)),
            static fn (): array => OperatorRole::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'description', 'default_hourly_rate', 'is_active', 'created_at', 'updated_at'])
                ->map(static fn (OperatorRole $role): array => $role->getAttributes())
                ->all(),
        );

        if (!is_array($roleRows)) {
            Cache::forget(self::ACTIVE_FORMS_KEY);

            return self::activeForForms();
        }

        /** @var Collection $roles */
        $roles = OperatorRole::hydrate($roleRows);

        return $roles;
    }

    public static function activeForService(?int $operatorRoleId = null): Collection
    {
        $roles = self::activeForForms();

        if (!$operatorRoleId || $roles->contains('id', $operatorRoleId)) {
            return $roles;
        }

        $selectedRole = OperatorRole::query()->find($operatorRoleId);

        if (!$selectedRole) {
            return $roles;
        }

        return $roles
            ->push($selectedRole)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public static function flush(): void
    {
        Cache::forget(self::ACTIVE_FORMS_KEY);
    }
}