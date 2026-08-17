<?php

namespace App\Support\Navigation\Groups;

class ReportesCajaNavigation
{
    /**
     * @return array<string, mixed>
     */
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('caja.ver') || $user?->is_super_admin) {
            $items[] = [
                'label'       => 'Resumen de caja',
                'description' => 'Total cobrado, entradas y salidas del período, desglosado por tipo.',
                'route'       => route('finances.cash-reports.resumen'),
                'debug_id'    => 'FinRepRes',
                'active'      => request()->routeIs('finances.cash-reports.resumen'),
            ];

            $items[] = [
                'label'       => 'Métodos de pago',
                'description' => 'Cobros del período agrupados por método (efectivo, tarjeta, SPEI...).',
                'route'       => route('finances.cash-reports.metodos-pago'),
                'debug_id'    => 'FinRepMet',
                'active'      => request()->routeIs('finances.cash-reports.metodos-pago'),
            ];

            $items[] = [
                'label'       => 'Por operador',
                'description' => 'Entradas y salidas del período agrupadas por quién las registró.',
                'route'       => route('finances.cash-reports.por-operador'),
                'debug_id'    => 'FinRepOpe',
                'active'      => request()->routeIs('finances.cash-reports.por-operador'),
            ];

            $items[] = [
                'label'       => 'Pendientes por cobrar',
                'description' => 'Citas terminadas o en proceso con saldo pendiente real, sin cobrar.',
                'route'       => route('finances.cash-reports.pendientes'),
                'debug_id'    => 'FinRepPen',
                'active'      => request()->routeIs('finances.cash-reports.pendientes'),
            ];

            $items[] = [
                'label'       => 'Cierre de turno',
                'description' => 'Historial de cortes de caja ya cerrados, con su diferencia real.',
                'route'       => route('finances.cash-reports.cierres'),
                'debug_id'    => 'FinRepCie',
                'active'      => request()->routeIs('finances.cash-reports.cierres'),
            ];
        }

        return [
            'label'  => 'Caja',
            'active' => request()->routeIs('finances.cash-reports.*'),
            'items'  => $items,
        ];
    }
}
