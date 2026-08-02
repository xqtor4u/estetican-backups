<?php

namespace App\Http\Middleware;

use App\Support\SystemSettings\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(SystemSettings::class);
        $configOverrides = $settings->configOverrides();

        if ($configOverrides !== []) {
            config($configOverrides);
        }

        // La zona horaria ya se fija una sola vez al boot de la app (AppServiceProvider::boot())
        // — hacerlo también aquí, por request, es lo que causaba que un now() calculado antes
        // de que corriera este middleware (ej. en un test, o en cualquier código que arma una
        // fecha antes de llegar al controlador) quedara en una zona horaria distinta del now()
        // que ve el controlador después de este punto.
        $locale = (string) config('backoffice.system.locale', config('app.locale', 'es'));
        $sessionLifetime = max(5, (int) config('backoffice.security.session_idle_minutes', config('session.lifetime', 120)));

        config([
            'app.locale' => $locale,
            'session.lifetime' => $sessionLifetime,
        ]);

        app()->setLocale($locale);

        $is24h = config('backoffice.system.time_format') === '24h';
        $dateFormat = (string) config('backoffice.system.date_format', 'd/m/Y');
        $timeFormat = $is24h ? 'H:i' : 'h:i A';

        view()->share([
            'dateFormat'     => $dateFormat,
            'timeFormat'     => $timeFormat,
            'datetimeFormat' => $dateFormat . ' ' . $timeFormat,
        ]);

        return $next($request);
    }
}