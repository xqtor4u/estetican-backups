<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Bloqueo de indexación (27/09/2026): robots.txt cierra todo y cada respuesta de Laravel lleva
 * X-Robots-Tag — incluidas las públicas (login) y la API.
 */
class NoIndexTest extends TestCase
{
    public function test_robots_txt_disallows_everything(): void
    {
        $this->assertSame("User-agent: *\nDisallow: /\n", file_get_contents(public_path('robots.txt')));
    }

    public function test_every_laravel_response_carries_the_noindex_header(): void
    {
        foreach (['/login', '/api/settings/branding', '/api/me'] as $uri) {
            $this->get($uri)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }
}
