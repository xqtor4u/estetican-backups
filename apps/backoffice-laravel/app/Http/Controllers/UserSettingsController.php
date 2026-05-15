<?php
// Created: 2026-04-22
// Standard: Modern Modular Backoffice

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\UserPhotoImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    protected UserPhotoImageManager $imageManager;

    public function __construct(UserPhotoImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }

    public function index()
    {
        $user = Auth::user();
        
        // Cargar permisos para la matriz de solo lectura
        $user->load('permissions');
        
        $modules = [
            'agenda' => ['label' => 'Agenda', 'code' => 'AGEIND'],
            'clientes' => ['label' => 'Clientes', 'code' => 'CLIIND'],
            'mascotas' => ['label' => 'Mascotas', 'code' => 'PETIND'],
            'sucursales' => ['label' => 'Recursos y Sucursales', 'code' => 'RESIND'],
            'catalogo_servicios' => ['label' => 'Catálogos', 'code' => 'CATALL'],
            'configuracion_sistema' => ['label' => 'Configuración', 'code' => 'SYSSET'],
            'usuarios' => ['label' => 'Usuarios', 'code' => 'USRIND'],
        ];
        
        $actions = [
            'ver' => 'Ver',
            'crear' => 'Crear',
            'editar' => 'Editar',
            'eliminar' => 'Borrar'
        ];

        return view('user.settings', [
            'user' => $user,
            'modules' => $modules,
            'actions' => $actions,
            'screenDebugId' => 'USRSET'
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users,name,' . $user->id,
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|image|max:5120',
        ]);

        $user->fill($validated);

        if ($request->hasFile('profile_photo')) {
            $this->imageManager->deleteFiles($user->profile_photo_path);
            $user->profile_photo_path = $this->imageManager->store($request->file('profile_photo'));
        }

        $user->save();

        return redirect()->back()->with('success', 'Perfil actualizado correctamente.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'current_password' => 'La contraseña actual no es correcta.',
            'confirmed' => 'La confirmación de la nueva contraseña no coincide.',
            'min' => 'La nueva contraseña debe tener al menos 6 caracteres.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', 'Contraseña actualizada correctamente.');
    }
}
