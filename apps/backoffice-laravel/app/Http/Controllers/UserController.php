<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OperatorRole;
use App\Support\UserPhotoImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected UserPhotoImageManager $imageManager;

    public function __construct(UserPhotoImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }
    // Solo admin puede ver todos los usuarios (USEIND)
    public function index()
    {
        $users = User::all();
        return view('user.index', compact('users'));
    }

    // Ver ficha técnica del usuario (USESHO)
    public function show($id)
    {
        $user = User::findOrFail($id);
        
        // Autorización: Propio perfil o Admin
        if (Auth::id() !== $user->id && !Auth::user()->is_super_admin) {
            abort(403);
        }
        
        return view('user.show', compact('user'));
    }

    // Formulario de alta de usuario (USECRE)
    public function create()
    {
        $operatorRoles = OperatorRole::where('is_active', true)
            ->orderBy('name')
            ->get();

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
            
        return view('user.create', compact('operatorRoles', 'modules', 'actions'));
    }

    // Guardar nuevo usuario (Fusión 14-Abr)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users,name',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'ine_number' => 'nullable|string|max:255',
            'imss_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'role' => ['required', Rule::in(['admin', 'operator'])],
            'is_active' => 'required|boolean',
            'can_login' => 'required|boolean',
            'is_operator' => 'required|boolean',
            'operator_code' => 'nullable|string|max:255|unique:users,operator_code',
            'operator_role_id' => 'nullable|exists:operator_roles,id',
            'notes' => 'nullable|string',
            'profile_photo' => 'nullable|image|max:5120',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'El :attribute debe ser un correo válido.',
            'unique' => 'Este :attribute ya está registrado.',
            'confirmed' => 'La confirmación de :attribute no coincide.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'exists' => 'El valor de :attribute seleccionado no existe.',
        ]);

        $user = new User();
        $user->fill($validated);
        
        if ($request->hasFile('profile_photo')) {
            $user->profile_photo_path = $this->imageManager->store($request->file('profile_photo'));
        }
        
        $user->password = Hash::make($validated['password']);
        $user->save();

        // Sincronizar Permisos Granulares
        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        }

        // Sincronizar sistema de roles (Spatie + Legacy)
        if ($validated['role'] === 'admin') {
            $user->assignRole('admin');
        } else {
            $user->removeRole('admin');
        }

        return redirect()->route('users.show', $user->id)
            ->with('success', 'Usuario creado correctamente y perfil operativo vinculado.');
    }

    // Formulario de edición (USEEDI)
    public function edit(User $user)
    {
        abort_unless(
            auth()->id() === $user->id || auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-admin'),
            403, 'No tienes permiso para editar este usuario.'
        );
        
        $operatorRoles = OperatorRole::where('is_active', true)
            ->orderBy('name')
            ->get();

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

        $userPermissions = $user->getPermissionNames()->toArray();
            
        return view('user.edit', compact('user', 'operatorRoles', 'modules', 'actions', 'userPermissions'));
    }

    // Actualizar usuario (Fusión 14-Abr)
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users,name,' . $user->id,
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6|confirmed',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'ine_number' => 'nullable|string|max:255',
            'imss_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'role' => ['required', Rule::in(['admin', 'operator'])],
            'is_active' => 'required|boolean',
            'can_login' => 'required|boolean',
            'is_operator' => 'required|boolean',
            'operator_code' => [
                'nullable', 
                'string', 
                Rule::unique('users')->ignore($user->id)
            ],
            'operator_role_id' => 'nullable|exists:operator_roles,id',
            'notes' => 'nullable|string',
            'profile_photo' => 'nullable|image|max:5120',
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'El :attribute debe ser un correo válido.',
            'unique' => 'Este :attribute ya está registrado.',
            'confirmed' => 'La confirmación de :attribute no coincide.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'exists' => 'El valor de :attribute seleccionado no existe.',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->fill($validated);
        
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            $this->imageManager->deleteFiles($user->profile_photo_path);
            $user->profile_photo_path = $this->imageManager->store($request->file('profile_photo'));
        }
        
        $user->save();
        
        // Sincronizar Permisos Granulares
        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        } else {
            $user->syncPermissions([]);
        }

        // Sincronizar roles (Spatie + Legacy)
        if ($validated['role'] === 'admin') {
            $user->assignRole('admin');
        } else {
            $user->removeRole('admin');
        }

        return redirect()->route('users.show', $user->id)
            ->with('success', 'Usuario actualizado correctamente.');
    }

    // Eliminar usuario
    public function destroy(User $user)
    {
        // Candado: No eliminarse a sí mismo
        if (Auth::id() === $user->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta administrativa.');
        }
        
        // Cleanup photos
        $this->imageManager->deleteFiles($user->profile_photo_path);
        
        $user->delete();
        
        return redirect()->route('users.index')
            ->with('success', 'El usuario ha sido eliminado permanentemente del sistema.');
    }
}
