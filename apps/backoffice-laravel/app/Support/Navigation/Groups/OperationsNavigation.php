<?php

namespace App\Support\Navigation\Groups;

class OperationsNavigation
{
    /**
     * @return array<string, mixed>
     */
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('ver agenda') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Agenda',
                'description' => 'Servicios de SPA, estancias de Hotel y disponibilidad operativa general.',
                'route' => route('agenda.index'),
                'active' => request()->routeIs('agenda.*'),
            ];
        }

        if ($user?->can('ver sucursales') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Recursos y activos',
                'description' => 'Jaulas, equipos, bloqueos, uso y mantenimiento.',
                'route' => route('resources.index'),
                'active' => request()->routeIs('resources.*'),
            ];
        }

        if ($user?->can('ver configuracion_sistema') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Configuración del sistema',
                'description' => 'Visualización, convenciones generales y controles ligeros de seguridad.',
                'route' => route('system-settings.index'),
                'active' => request()->routeIs('system-settings.*'),
            ];
        }

        $isOperationsActive = request()->routeIs('agenda.*') || request()->routeIs('resources.*') || request()->routeIs('branches.*') || request()->routeIs('system-settings.*');

        return [
            'label' => 'Operación',
            'active' => $isOperationsActive,
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
                'label' => 'Agenda',
                'route' => route('agenda.index'),
                'active' => request()->routeIs('agenda.*'),
            ],
            [
                'label' => 'Recursos y activos',
                'route' => route('resources.index'),
                'active' => request()->routeIs('resources.*'),
            ],
            [
                'label' => 'Configuración del sistema',
                'route' => route('system-settings.index'),
                'active' => request()->routeIs('system-settings.*'),
            ],
        ];
    }
}