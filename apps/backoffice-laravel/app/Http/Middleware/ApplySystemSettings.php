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

        $locale = (string) config('backoffice.system.locale', config('app.locale', 'es'));
        $timezone = (string) config('backoffice.system.timezone', config('app.timezone', 'UTC'));
        $sessionLifetime = max(5, (int) config('backoffice.security.session_idle_minutes', config('session.lifetime', 120)));

        config([
            'app.locale' => $locale,
            'app.timezone' => $timezone,
            'session.lifetime' => $sessionLifetime,
        ]);

        app()->setLocale($locale);
        date_default_timezone_set($timezone);

        return $next($request);
    }
}