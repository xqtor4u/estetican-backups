<?php

namespace App\Http\Middleware;

use App\Support\SystemSettings\SystemSettings;
use Closure;
use Illuminate\Http\Request;

class VerifyAssistantSiteToken
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(SystemSettings::class)->all();

        if (! $settings['ai_assistant_enabled']) {
            return response()->json(['message' => 'Asistente no disponible'], 404);
        }

        $expected = $settings['ai_assistant_site_token'];

        if (! $expected || $request->header('X-Widget-Token') !== $expected) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}
