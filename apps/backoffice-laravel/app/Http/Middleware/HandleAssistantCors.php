<?php

namespace App\Http\Middleware;

use App\Support\SystemSettings\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS a medida para las rutas del asistente: el origen permitido vive en
 * SystemSettings (base de datos), no en config/cors.php — un archivo de
 * config estático no puede leer la BD de forma segura porque se carga antes
 * de que Laravel registre los proveedores de base de datos/caché.
 *
 * Va registrado como middleware GLOBAL (bootstrap/app.php), no de ruta:
 * el preflight OPTIONS que manda el navegador nunca llegaría a un
 * middleware de ruta, porque Laravel rechaza el método antes de resolver
 * la ruta si esta solo registra GET/POST. Por eso se acota aquí mismo por
 * path en vez de por grupo de rutas.
 */
class HandleAssistantCors
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/assistant/*')) {
            return $next($request);
        }

        $allowedOrigin = app(SystemSettings::class)->all()['ai_assistant_allowed_origin'];
        $origin = $request->headers->get('Origin');

        $response = $request->getMethod() === 'OPTIONS'
            ? response('', 204)
            : $next($request);

        if ($allowedOrigin && $origin === $allowedOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Widget-Token');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        }

        return $response;
    }
}
