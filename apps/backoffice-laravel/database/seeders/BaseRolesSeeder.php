<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class BaseRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Definir Módulos y sus permisos CRUD
        $modules = [
            'agenda',
            'catalogo_servicios',
            'clientes',
            'configuracion_sistema',
            'hotel',
            'mascotas',
            'operadores',
            'sucursales',
            'usuarios',
        ];

        $actions = ['ver', 'crear', 'editar', 'eliminar'];
        $permissions = ['administrar todo'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permissions[] = "{$action} {$module}";
            }
        }

        // Permisos granulares del módulo financiero
        $financialPermissions = [
            'cobros.registrar',
            'caja.abrir',
            'caja.cerrar',
            'asientos.aprobar',
        ];

        $permissions = array_merge($permissions, $financialPermissions);

        // 2. Crear/Asegurar Permisos
        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // 3. Crear rol admin si no existe y asignar TODOS los permisos
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissions);

        // 4. Asegurar que los usuarios con rol 'admin' (legacy) tengan el rol de Spatie
        $legacyAdmins = User::where('role', 'admin')->get();

        foreach ($legacyAdmins as $user) {
            if (!$user->hasRole('admin')) {
                $user->assignRole($adminRole);
            }
        }
    }
}
