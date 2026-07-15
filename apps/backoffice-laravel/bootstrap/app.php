<?php

use App\Http\Middleware\ApplySystemSettings;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureClinicalModuleEnabled;
use App\Http\Middleware\HandleAssistantCors;
use App\Http\Middleware\ProfileBackofficeRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up/'.env('HEALTH_CHECK_SECRET', 'disabled'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->prepend(HandleAssistantCors::class);
        $middleware->append(ContentSecurityPolicy::class);
        $middleware->append(ApplySystemSettings::class);
        $middleware->append(ProfileBackofficeRequests::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'clinical.module' => EnsureClinicalModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthorizationException $e, $request) {
            return redirect()->back()->withErrors(['error' => 'No tienes permiso para realizar esta acción.']);
        });
    })->create();
