<?php

namespace App\Http\Middleware;

use App\Support\SystemSettings\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnsureClinicalModuleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(SystemSettings::class)->all();

        if (! $settings['clinical_module_enabled']) {
            throw new NotFoundHttpException('El módulo de Veterinaria no está activo.');
        }

        return $next($request);
    }
}
