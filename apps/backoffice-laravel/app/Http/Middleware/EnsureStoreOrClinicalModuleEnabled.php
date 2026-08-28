<?php

namespace App\Http\Middleware;

use App\Support\SystemSettings\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * El catálogo de artículos (`items.*`) es compartido: Tienda lo usa para venta al público,
 * Veterinaria lo usa para Farmacia (medicamentos, ver BL-071) y Vacunas (BL-048) — un mismo
 * `Item` con `department` distinto, nunca tablas separadas. Antes de esto, `items.*` vivía
 * detrás de `store.module` únicamente: una clínica con Veterinaria activa pero Tienda apagada
 * no podía dar de alta ni ver sus propios medicamentos (404 real, aunque el rol `veterinario`
 * ya tenía el permiso `ver/crear catalogo_articulos` desde `ClinicalRolesSeeder` — el bloqueo
 * era de módulo, no de permiso). Hallazgo real 16/08/2026, a pedido del usuario.
 */
class EnsureStoreOrClinicalModuleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(SystemSettings::class)->all();

        if (! $settings['store_module_enabled'] && ! $settings['clinical_module_enabled']) {
            throw new NotFoundHttpException('El catálogo de artículos requiere el módulo de Tienda o el de Veterinaria activo.');
        }

        return $next($request);
    }
}
