<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = \App\Models\User::where('name', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Usuario inactivo'], 403);
        }

        $plain  = Str::random(40);
        $hashed = hash('sha256', $plain);

        ApiToken::create([
            'user_id' => $user->id,
            'token'   => $hashed,
            'name'    => 'mobile',
        ]);

        $roles = $user->getRoleNames()->toArray();

        return response()->json([
            'token' => $plain,
            'user'  => [
                'id'    => $user->id,
                'name'  => trim(($user->first_name ?? $user->name) . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
                'roles' => $roles,
                'is_admin' => $user->is_super_admin,
                'operator_role' => $user->operatorRole?->name,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            ApiToken::where('token', hash('sha256', $bearer))->delete();
        }
        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $user = auth()->user();
        return response()->json([
            'id'    => $user->id,
            'name'  => trim(($user->first_name ?? $user->name) . ' ' . ($user->last_name ?? '')),
            'email' => $user->email,
            'roles' => $user->getRoleNames()->toArray(),
            'is_admin' => $user->is_super_admin,
            'operator_role' => $user->operatorRole?->name,
        ]);
    }
}
