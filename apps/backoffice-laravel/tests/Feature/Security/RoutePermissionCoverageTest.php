<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A3 (27/09/2026) — convierte la regla de seguridad #3 de CLAUDE.md ("toda ruta de negocio lleva
 * `permission:`") de "recordarlo a mano al cerrar la sesión" a "este test falla si se olvida".
 *
 * Toda ruta registrada debe llevar `permission:`, `role:` o `superadmin`, o estar en ALLOWED con
 * su razón. Categorías (las mismas de la regla #3):
 *   (a) pública a propósito · (b) protegida por otro mecanismo (firma, token propio)
 *   (c) cualquier usuario autenticado: datos del propio usuario o catálogos que toda pantalla
 *       necesita — nunca datos de negocio de otros.
 *
 * Si este test falla por una ruta nueva: casi siempre la respuesta es agregarle `permission:`.
 * Solo si de verdad cae en (a)/(b)/(c), agrégala aquí con su razón. Una ruta de la lista que
 * todavía no existe en este repo (p. ej. aún no portada) no es error.
 */
class RoutePermissionCoverageTest extends TestCase
{
    /** "MÉTODOS uri" (sin HEAD) => razón. */
    private const ALLOWED = [
        // (a) Públicas a propósito
        'GET /' => '(a) redirige a login o a la primera sección permitida',
        'GET login' => '(a) formulario de inicio de sesión',
        'POST login' => '(a) inicio de sesión (throttle:login)',
        'POST logout' => '(a) cierre de sesión',
        'POST api/login' => '(a) inicio de sesión móvil (throttle:login)',
        'GET api/settings/branding' => '(a) nombre/logo del negocio para la pantalla de login móvil',
        'GET up/disabled' => '(a) health check deshabilitado (BL-006)',

        // (b) Protegidas por otro mecanismo
        'GET preferencias/{client}' => '(b) link firmado del correo al cliente',
        'POST preferencias/{client}' => '(b) link firmado del correo al cliente',
        'GET documentos/{document}/{id}' => '(b) link firmado que caduca, PDF enviado por WhatsApp (A1)',
        'GET storage/{path}' => '(b) Laravel ServeFile: valida la firma dentro del controlador',
        'PUT storage/{path}' => '(b) Laravel ReceiveFile: valida la firma dentro del controlador',
        'GET api/assistant/config' => '(b) token del sitio (VerifyAssistantSiteToken), widget de WP',
        'POST api/assistant/chat' => '(b) token del sitio (VerifyAssistantSiteToken) + throttle',

        // (c) Cualquier usuario autenticado — su propia cuenta o catálogos comunes
        'GET bloqueo' => '(c) pantalla de bloqueo de la propia sesión',
        'POST bloqueo' => '(c) bloquear la propia sesión',
        'POST bloqueo/desbloquear' => '(c) desbloquear la propia sesión (throttle)',
        'GET user/settings' => '(c) configuración personal',
        'PUT user/settings' => '(c) configuración personal',
        'PUT user/settings/password' => '(c) contraseña propia',
        'PUT user/settings/preferences' => '(c) preferencias propias',
        'GET api/me' => '(c) perfil propio (móvil)',
        'PATCH api/me' => '(c) perfil propio (móvil)',
        'PUT api/me/password' => '(c) contraseña propia (móvil)',
        'PATCH api/me/preferences' => '(c) preferencias propias (móvil, ZEUS-040)',
        'POST api/me/photo' => '(c) foto propia (móvil)',
        'DELETE api/me/photo' => '(c) foto propia (móvil)',
        'POST api/me/verify-password' => '(c) desbloqueo local de la app con la contraseña propia',
        'POST api/logout' => '(c) cerrar la propia sesión móvil',
        'GET api/checkin/status' => '(c) asistencia propia del operador',
        'POST api/checkin' => '(c) registrar la propia entrada',
        'POST api/checkout' => '(c) registrar la propia salida',
        'GET api/payment-methods' => '(c) catálogo de métodos de pago (sin datos de clientes)',
        'GET api/settings/booking' => '(c) horario/tolerancias del negocio para agendar',
        'GET api/settings/phone-format' => '(c) formato de teléfono configurado',
        'GET api/settings/photos' => '(c) parámetros de subida de fotos',
        'GET api/work-order-types' => '(c) catálogo de tipos de orden',
    ];

    public function test_every_route_has_a_permission_or_a_documented_reason(): void
    {
        $unprotected = $this->unprotectedRoutes();

        $this->assertTrue(
            $unprotected->isEmpty(),
            "Rutas sin permission:/role:/superadmin y sin razón documentada:\n  - "
                .$unprotected->implode("\n  - ")
                ."\nAgrégales permission: (regla de seguridad #1 de CLAUDE.md) o, si de verdad son"
                .' (a) públicas, (b) protegidas por otro mecanismo o (c) de la propia cuenta,'
                .' documéntalas en RoutePermissionCoverageTest::ALLOWED.'
        );
    }

    public function test_it_actually_catches_a_business_route_without_permission(): void
    {
        Route::middleware(['web', 'auth'])->get('zz-prueba-sin-permiso', fn () => 'x');
        Route::middleware(['web', 'auth', 'permission:ver agenda'])->get('zz-prueba-con-permiso', fn () => 'x');
        Route::getRoutes()->refreshNameLookups();

        $unprotected = $this->unprotectedRoutes();

        $this->assertContains('GET zz-prueba-sin-permiso', $unprotected->all());
        $this->assertNotContains('GET zz-prueba-con-permiso', $unprotected->all());
    }

    public function test_every_documented_reason_names_its_category(): void
    {
        foreach (self::ALLOWED as $key => $reason) {
            $this->assertMatchesRegularExpression('/^\((a|b|c)\) \S/', $reason, "Razón sin categoría para: $key");
        }
    }

    /** @return Collection<int, string> */
    private function unprotectedRoutes(): Collection
    {
        return collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RouteInstance $route) => $this->isGated($route))
            ->map(fn (RouteInstance $route) => $this->key($route))
            ->reject(fn (string $key) => array_key_exists($key, self::ALLOWED))
            ->values();
    }

    private function isGated(RouteInstance $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && preg_match('/^(permission:|role:|superadmin$)/', $middleware)) {
                return true;
            }
        }

        return false;
    }

    private function key(RouteInstance $route): string
    {
        return implode('|', array_values(array_diff($route->methods(), ['HEAD']))).' '.$route->uri();
    }
}
