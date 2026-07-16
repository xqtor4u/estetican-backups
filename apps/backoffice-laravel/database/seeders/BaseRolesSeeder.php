<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BaseRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Definir Módulos y sus permisos CRUD
        $modules = [
            'agenda',
            'catalogo_servicios',
            'catalogo_articulos',
            'catalogo_grupos',
            'clientes',
            'configuracion_sistema',
            'hotel',
            'mascotas',
            'operadores',
            'sucursales',
            'usuarios',
            'whatsapp',
            'clinico',
        ];

        $actions = ['ver', 'crear', 'editar', 'eliminar'];
        $permissions = ['administrar todo'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permissions[] = "{$action} {$module}";
            }
        }

        // Permisos granulares fuera del patrón CRUD básico
        $granularPermissions = [
            'cobros.registrar',
            'caja.abrir',
            'caja.cerrar',
            'asientos.aprobar',
            'clinico.firmar',
            'alergias.administrar',
        ];

        $permissions = array_merge($permissions, $granularPermissions);

        // 2. Crear/Asegurar Permisos
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // 3. Crear rol admin si no existe y asignar TODOS los permisos
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissions);

        // 4. Asegurar que los usuarios con rol 'admin' (legacy) tengan el rol de Spatie
        $legacyAdmins = User::where('role', 'admin')->get();

        foreach ($legacyAdmins as $user) {
            if (! $user->hasRole('admin')) {
                $user->assignRole($adminRole);
            }
        }
    }
}
