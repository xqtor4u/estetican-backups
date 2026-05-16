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