<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\UserPhotoImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(private UserPhotoImageManager $imageManager)
    {
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'email'      => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json($user->fresh()->toApiArray());
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|string|min:6|confirmed',
        ], [
            'current_password' => 'La contraseña actual no es correcta.',
            'confirmed'        => 'La confirmación de la nueva contraseña no coincide.',
            'min'              => 'La nueva contraseña debe tener al menos 6 caracteres.',
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['ok' => true]);
    }

    /** Verifica la contraseña sin cerrar la sesión actual — usado por la pantalla de bloqueo de la app móvil */
    public function verifyPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ], [
            'password' => 'Contraseña incorrecta.',
        ]);

        return response()->json(['ok' => true]);
    }

    public function updatePhoto(Request $request)
    {
        $user = Auth::user();

        $request->validate(['photo' => 'required|image|max:5120']);

        $this->imageManager->deleteFiles($user->profile_photo_path);
        $user->profile_photo_path = $this->imageManager->store($request->file('photo'));
        $user->save();

        return response()->json($user->fresh()->toApiArray());
    }

    public function deletePhoto()
    {
        $user = Auth::user();

        $this->imageManager->deleteFiles($user->profile_photo_path);
        $user->profile_photo_path = null;
        $user->save();

        return response()->json($user->fresh()->toApiArray());
    }
}
