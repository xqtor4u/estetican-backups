<?php

namespace App\Support\CatalogCache;

use Illuminate\Support\Facades\Cache;

/**
 * Cache de la resolución "¿qué servicios puede hacer este operador?" (SYNC-073). Guarda un set
 * de `service_id` por operador; `flush()` invalida todo de un jalón subiendo una clave de
 * versión, sin tener que enumerar operadores.
 *
 * Se invalida (vía `flush()`) desde `booted()` de:
 *  - OperatorServiceCapability (alta/baja de capacidad directa)
 *  - OperatorRoleServiceTemplate (edición de plantilla de rol)
 *  - OperatorRoleAssignment (cambio de asignación de rol de un operador)
 *  - Service (toggle `open_to_all_operators`)
 */
class OperatorServiceCapabilityCache
{
    private const VERSION_KEY = 'backoffice.catalogs.operator-service-capabilities.version';

    /** Token de versión: cualquier cambio de config lo mueve, invalidando todas las entradas. */
    private static function version(): string
    {
        return (string) Cache::rememberForever(self::VERSION_KEY, static fn () => '1');
    }

    private static function operatorKey(int $operatorId): string
    {
        return 'backoffice.catalogs.operator-service-capabilities.'.self::version().".op{$operatorId}";
    }

    /**
     * @param  callable():array<int>  $resolve  devuelve los service_id que el operador puede hacer
     * @return array<int>
     */
    public static function rememberForOperator(int $operatorId, callable $resolve): array
    {
        $ids = Cache::remember(
            self::operatorKey($operatorId),
            now()->addSeconds((int) config('backoffice.cache.catalog_ttl_seconds', 1800)),
            static fn (): array => array_values(array_map('intval', $resolve())),
        );

        return is_array($ids) ? $ids : array_values(array_map('intval', $resolve()));
    }

    public static function flush(): void
    {
        // Nueva versión = nuevo prefijo de clave; las entradas viejas quedan huérfanas y expiran solas.
        Cache::forever(self::VERSION_KEY, uniqid('', true));
    }
}
