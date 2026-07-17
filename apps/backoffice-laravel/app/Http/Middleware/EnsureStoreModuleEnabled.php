<?php

namespace App\Http\Middleware;

use App\Support\SystemSettings\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnsureStoreModuleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(SystemSettings::class)->all();

        if (! $settings['store_module_enabled']) {
            throw new NotFoundHttpException('El módulo de Tienda no está activo.');
        }

        return $next($request);
    }
}
