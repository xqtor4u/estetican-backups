<?php

namespace Database\Seeders;

use App\Models\OperatorRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClinicalRolesSeeder extends Seeder
{
    /**
     * Permisos y rol de sistema (Spatie) + rol de ejecución (operator_roles) para
     * el módulo de expediente clínico veterinario. Idempotente.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Módulo "clinico" con el mismo patrón CRUD que los demás módulos (BaseRolesSeeder)
        $permissions = [
            'ver clinico',
            'crear clinico',
            'editar clinico',
            'eliminar clinico',
            // Acciones granulares fuera del CRUD básico, mismo patrón que los permisos financieros
            'clinico.firmar',
            'alergias.administrar',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // 'catalogo_articulos' vive en BaseRolesSeeder (módulo compartido, no exclusivo de clínico) —
        // firstOrCreate acá también por si este seeder corre antes que aquel.
        $itemPermissions = ['ver catalogo_articulos', 'crear catalogo_articulos'];
        foreach ($itemPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $veterinarioRole = Role::firstOrCreate(['name' => 'veterinario', 'guard_name' => 'web']);
        $veterinarioRole->syncPermissions(array_merge($permissions, $itemPermissions, ['ver mascotas', 'ver clientes']));

        OperatorRole::firstOrCreate(
            ['code' => 'veterinario'],
            [
                'acronym' => 'VET',
                'name' => 'Veterinario',
                'can_login' => true,
            ]
        );
    }
}
