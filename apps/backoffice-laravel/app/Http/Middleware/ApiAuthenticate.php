<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;

class ApiAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $hashed = hash('sha256', $bearer);
        $apiToken = ApiToken::with('user')->where('token', $hashed)->first();

        if (! $apiToken || ! $apiToken->user) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $user = $apiToken->user;

        if (! $user->is_active || ! $user->can_login) {
            return response()->json(['message' => 'Acceso desactivado'], 403);
        }

        $apiToken->update(['last_used_at' => now()]);

        auth()->login($user);
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
