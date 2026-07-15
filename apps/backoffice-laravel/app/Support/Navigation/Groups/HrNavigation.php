<?php

namespace App\Support\Navigation\Groups;

class HrNavigation
{
    /**
     * @return array<string, mixed>
     */
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('ver operadores') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Operadores',
                'description' => 'Catálogo del personal operativo con trazabilidad de trabajos realizados.',
                'route' => route('operators.index'),
                'active' => request()->routeIs('operators.index', 'operators.show'),
            ];
        }

        if ($user?->can('ver sucursales') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Tipos de operador',
                'description' => 'Catálogo canónico de especialidades con clave, descripción y tarifa base.',
                'route' => route('operator-roles.index'),
                'active' => request()->routeIs('operator-roles.index', 'operator-roles.show'),
            ];
        }

        if ($user?->can('ver usuarios') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Usuarios',
                'description' => 'Administrar usuarios del sistema.',
                'route' => route('users.index'),
                'active' => request()->routeIs('users.*'),
            ];
        }

        return [
            'label' => 'RH',
            'active' => request()->routeIs('operators.*', 'operator-roles.*', 'users.*'),
            'items' => $items,
        ];
    }
}
