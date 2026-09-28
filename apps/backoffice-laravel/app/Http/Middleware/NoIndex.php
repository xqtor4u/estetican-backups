<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ningún buscador ni bot debe indexar el backoffice ni la API (27/09/2026). robots.txt es solo
 * una petición; este encabezado impide que una página aparezca en buscadores aunque alguien la
 * enlace desde otro sitio. Aplica a todo lo que pasa por Laravel (páginas, API, PDF firmados);
 * los archivos estáticos que sirve nginx directo quedan cubiertos por robots.txt y Cloudflare.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
