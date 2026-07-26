<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScreenLockController extends Controller
{
    public function show(Request $request)
    {
        if (! $request->session()->get('screen_lock.locked', false)) {
            return redirect()->route('dashboard.index');
        }

        return view('auth.locked', [
            'lockedByName' => Auth::user()->name,
        ]);
    }

    public function lock(Request $request)
    {
        $validated = $request->validate([
            'redirect_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $request->session()->put('screen_lock.locked', true);
        $request->session()->put('screen_lock.return_to', $this->safePath($validated['redirect_url'] ?? null));

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('screen-lock.show')]);
        }

        return redirect()->route('screen-lock.show');
    }

    public function unlock(Request $request)
    {
        if (! $request->session()->get('screen_lock.locked', false)) {
            return redirect()->route('dashboard.index');
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ], [
            'password.current_password' => 'La contraseña no es correcta.',
        ]);

        $returnTo = $request->session()->get('screen_lock.return_to');

        $request->session()->forget(['screen_lock.locked', 'screen_lock.return_to']);

        return redirect()->to($this->safeReturnTo($returnTo));
    }

    /**
     * Solo acepta rutas internas relativas (empiezan con "/" simple, sin
     * protocolo ni doble slash) para evitar un open redirect vía redirect_url.
     */
    private function safePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '://')) {
            return null;
        }

        return $path;
    }

    private function safeReturnTo(?string $returnTo): string
    {
        $path = $this->safePath($returnTo);

        if (! $path || str_starts_with($path, '/bloqueo') || str_starts_with($path, '/login')) {
            return route('dashboard.index');
        }

        return url($path);
    }
}
