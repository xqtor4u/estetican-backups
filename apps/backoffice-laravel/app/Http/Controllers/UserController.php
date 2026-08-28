<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Operator;
use App\Models\OperatorRole;
use App\Models\User;
use App\Support\UserPhotoImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * "Disponibilidad propia" se maneja como un switch simple en vez de exponerse en la
     * matriz CRUD genérica — ver/crear/eliminar se otorgan siempre juntos (no hay acción
     * de "editar" un bloqueo, solo alta/borrado, ver NT-041) y un checkbox por acción
     * confundía más de lo que ayudaba.
     */
    private const OWN_AVAILABILITY_PERMISSIONS = [
        'ver disponibilidad_propia',
        'crear disponibilidad_propia',
        'eliminar disponibilidad_propia',
    ];

    /**
     * Permisos granulares de Caja (SYNC-024) — antes de esto no había forma de otorgarle
     * ninguno a un usuario no-admin desde esta pantalla (ni siquiera los que ya existían,
     * `caja.abrir`/`caja.cerrar`); ahora que Caja depende de estos en vez del check-in, hace
     * falta poder asignarlos de verdad, junto con la sucursal.
     */
    private const CAJA_PERMISSIONS = [
        'caja.ver' => 'Ver caja y reportes',
        'caja.abrir' => 'Abrir turno',
        'caja.cerrar' => 'Cerrar turno',
        'caja.movimientos.crear' => 'Registrar movimientos',
        'caja.movimientos.editar' => 'Editar movimientos (concepto/notas)',
        'caja.movimientos.revertir' => 'Revertir movimientos',
    ];

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
        if (Auth::id() !== $user->id && ! Auth::user()->is_super_admin) {
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
            'eliminar' => 'Borrar',
        ];

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $cajaPermissions = self::CAJA_PERMISSIONS;

        return view('user.create', compact('operatorRoles', 'modules', 'actions', 'branches', 'cajaPermissions'));
    }

    // Guardar nuevo usuario (Fusión 14-Abr)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users,name',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'first_name' => 'nullable|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
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
            'branch_id' => 'nullable|exists:branches,id',
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

        $user = new User;
        $user->fill($validated);

        if ($request->hasFile('profile_photo')) {
            $user->profile_photo_path = $this->imageManager->store($request->file('profile_photo'));
        }

        $user->password = Hash::make($validated['password']);
        $user->save();
        $this->syncOperatorRecord($user);

        // Sincronizar Permisos Granulares (matriz CRUD + switch de disponibilidad propia)
        $permissions = (array) $request->input('permissions', []);
        if ($request->boolean('can_manage_own_availability')) {
            $permissions = array_values(array_unique(array_merge($permissions, self::OWN_AVAILABILITY_PERMISSIONS)));
        }
        $user->syncPermissions($permissions);

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
            'eliminar' => 'Borrar',
        ];

        $userPermissions = $user->getPermissionNames()->toArray();
        $canManageOwnAvailability = $user->hasPermissionTo('crear disponibilidad_propia');
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $cajaPermissions = self::CAJA_PERMISSIONS;

        return view('user.edit', compact('user', 'operatorRoles', 'modules', 'actions', 'userPermissions', 'canManageOwnAvailability', 'branches', 'cajaPermissions'));
    }

    // Actualizar usuario (Fusión 14-Abr)
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users,name,'.$user->id,
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6|confirmed',
            'first_name' => 'nullable|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
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
                Rule::unique('users')->ignore($user->id),
            ],
            'operator_role_id' => 'nullable|exists:operator_roles,id',
            'branch_id' => 'nullable|exists:branches,id',
            'notes' => 'nullable|string',
            'profile_photo' => 'nullable|image|max:5120',
            'google_personal_email' => 'nullable|email|max:255',
            'google_calendar_visibility' => ['nullable', Rule::in(['personal', 'all'])],
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
        $this->syncOperatorRecord($user);

        // Sincronizar Permisos Granulares (matriz CRUD + switch de disponibilidad propia)
        $permissions = (array) $request->input('permissions', []);
        if ($request->boolean('can_manage_own_availability')) {
            $permissions = array_values(array_unique(array_merge($permissions, self::OWN_AVAILABILITY_PERMISSIONS)));
        }
        $user->syncPermissions($permissions);

        // Sincronizar roles (Spatie + Legacy)
        if ($validated['role'] === 'admin') {
            $user->assignRole('admin');
        } else {
            $user->removeRole('admin');
        }

        return redirect()->route('users.show', $user->id)
            ->with('success', 'Usuario actualizado correctamente.');
    }

    private function syncOperatorRecord(User $user): void
    {
        if (! $user->is_operator) {
            if ($user->operator_id) {
                Operator::where('id', $user->operator_id)->update(['is_active' => false]);
            }

            return;
        }

        $data = [
            'code' => $user->operator_code ?: strtoupper(substr($user->name, 0, 8)),
            'name' => $user->name,
            'first_name' => $user->first_name ?: $user->name,
            'apellido_paterno' => $user->apellido_paterno,
            'apellido_materno' => $user->apellido_materno,
            // El rol del operador dejó de vivir en `operators.operator_role_id` desde la
            // migración 2026_07_31_000000_consolidate_operator_role_fields (ahora está en
            // `operator_role_assignments`, se maneja desde la pantalla de operador). Escribir
            // esa columna aquí tiraba un 500 (columna inexistente) al guardar un usuario ya
            // vinculado a un operador. `users.operator_role_id` sigue guardándose aparte.
            'ine_number' => $user->ine_number,
            'imss_number' => $user->imss_number,
            'address' => $user->address,
            'phone' => $user->phone,
            'profile_photo_path' => $user->profile_photo_path,
            'emergency_contact_name' => $user->emergency_contact_name,
            'emergency_contact_phone' => $user->emergency_contact_phone,
            'hire_date' => $user->hire_date,
            'is_active' => $user->is_active,
            'notes' => $user->notes,
        ];

        if ($user->operator_id) {
            Operator::where('id', $user->operator_id)->update($data);
        } else {
            $operator = Operator::create($data);
            $user->operator_id = $operator->id;
            $user->saveQuietly();
        }
    }

    // Eliminar usuario (o desactivar si tiene historial asociado, ver BL-066)
    public function destroy(User $user)
    {
        // Candado: No eliminarse a sí mismo
        if (Auth::id() === $user->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta administrativa.');
        }

        if ($user->hasHistoricalDependencies()) {
            $user->update(['is_active' => false, 'can_login' => false]);

            return redirect()->route('users.index')
                ->with('success', 'El usuario tiene historial asociado (citas, movimientos de caja, registros de actividad, etc.), así que se marcó como inactivo en vez de eliminarlo para no perder ese historial.');
        }

        // Cleanup photos
        $this->imageManager->deleteFiles($user->profile_photo_path);

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'El usuario ha sido eliminado permanentemente del sistema.');
    }
}
