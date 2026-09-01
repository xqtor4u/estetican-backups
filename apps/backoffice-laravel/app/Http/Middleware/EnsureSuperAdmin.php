<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Acceso al área de administración del sistema (Usuarios, Configuración, Bitácora de
 * actividad, Finanzas). Usa `User::is_super_admin` — la fuente de verdad "híbrida"
 * (rol Spatie `admin`/`super-admin` **o** la columna legacy `users.role = 'admin'`) —
 * en vez de `role:admin|super-admin`, que solo mira los roles de Spatie y dejaba fuera
 * a los super admins creados por un camino que no asigna el rol Spatie (aprovisionamiento
 * de tenant, orden de seeders, alta directa en BD). El resto de la app ya se apoya en
 * `is_super_admin`; esto lo alinea.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(
            (bool) $request->user()?->is_super_admin,
            403,
            'Solo un administrador del sistema puede acceder a esta sección.'
        );

        return $next($request);
    }
}
