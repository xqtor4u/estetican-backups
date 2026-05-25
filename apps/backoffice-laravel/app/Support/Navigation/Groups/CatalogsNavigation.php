<?php

namespace App\Support\Navigation\Groups;

class CatalogsNavigation
{
    /**
     * @return array<string, mixed>
     */
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('ver catalogo_servicios') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Servicios',
                'description' => 'Catálogo comercial base con descripción, precio sugerido y duración.',
                'route' => route('services.index'),
                'active' => request()->routeIs('services.index', 'services.show'),
            ];
        }

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
            
            $items[] = [
                'label' => 'Sucursales',
                'description' => 'Catálogo de bases operativas para asignación controlada de personal y futura operación multisucursal.',
                'route' => route('branches.index'),
                'active' => request()->routeIs('branches.index', 'branches.show'),
            ];
        }

        if ($user?->can('ver usuarios') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Usuarios',
                'description' => 'Administrar usuarios del sistema.',
                'route' => route('users.index'),
                'active' => request()->routeIs('users.*'),
            ];

            $items[] = [
                'label' => 'Bitácora de actividad',
                'description' => 'Registro de todas las operaciones realizadas por los usuarios.',
                'route' => route('activity-log.index'),
                'active' => request()->routeIs('activity-log.*'),
            ];
        }

        return [
            'label' => 'Catálogos',
            'active' => request()->routeIs('services.*', 'operators.*', 'operator-roles.*', 'branches.*', 'users.*', 'activity-log.*'),
            'items' => $items,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function mobileLinks(): array
    {
        return [
            [
                'label' => 'Servicios',
                'route' => route('services.index'),
                'active' => request()->routeIs('services.index', 'services.show'),
            ],
            [
                'label' => 'Operadores',
                'route' => route('operators.index'),
                'active' => request()->routeIs('operators.index', 'operators.show'),
            ],
            [
                'label' => 'Tipos de operador',
                'route' => route('operator-roles.index'),
                'active' => request()->routeIs('operator-roles.index', 'operator-roles.show'),
            ],
            [
                'label' => 'Sucursales',
                'route' => route('branches.index'),
                'active' => request()->routeIs('branches.index', 'branches.show'),
            ],
        ];
    }
}