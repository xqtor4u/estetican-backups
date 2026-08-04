<?php

namespace App\Support\Navigation\Groups;

class ReportesNavigation
{
    /**
     * @return array<string, mixed>
     */
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('ver usuarios') || $user?->is_super_admin) {
            $items[] = [
                'label' => 'Bitácora de actividad',
                'description' => 'Registro de todas las operaciones realizadas por los usuarios.',
                'route' => route('activity-log.index'),
                'active' => request()->routeIs('activity-log.*'),
            ];
        }

        return [
            'label' => 'Reportes',
            'active' => request()->routeIs('activity-log.*'),
            'items' => $items,
        ];
    }
}
