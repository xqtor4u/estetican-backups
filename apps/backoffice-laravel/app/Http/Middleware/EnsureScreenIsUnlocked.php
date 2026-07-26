<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureScreenIsUnlocked
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->get('screen_lock.locked', false)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'La sesión está bloqueada.'], 423);
        }

        return redirect()->route('screen-lock.show');
    }
}
