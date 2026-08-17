<?php

namespace App\Support\Navigation\Groups;

class ReportesNavigation
{
    /**
     * Un solo lugar para todos los reportes, organizado por subcategorías (mismo patrón ya
     * usado en "Operaciones del negocio" — ver `MainNavigation::operacionesDelNegocioGroup()`).
     * "General" arranca solo con Bitácora de actividad; "Caja" son los 5 reportes de Caja
     * (mismos datos que ya existen en el celular, ver `CashReportService`). Cuando se construya
     * BL-008 (reportes PDF de presupuestos/órdenes/facturas), va como subgrupo nuevo acá.
     *
     * @return array<string, mixed>
     */
    public static function group(): array
    {
        return [
            'label'     => 'Reportes',
            'subgroups' => [
                static::generalGroup(),
                ReportesCajaNavigation::group(),
            ],
        ];
    }

    private static function generalGroup(): array
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
            'label'  => 'General',
            'active' => request()->routeIs('activity-log.*'),
            'items'  => $items,
        ];
    }
}
