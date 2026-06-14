<?php

namespace App\Support\Navigation\Groups;

class FinanzasNavigation
{
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        if ($user?->can('cobros.registrar') || $user?->is_super_admin) {
            $items[] = [
                'label'       => 'Catálogo de cuentas',
                'description' => 'Árbol de cuentas contables: activos, pasivos, ingresos y gastos.',
                'route'       => route('finances.accounts.index'),
                'debug_id'    => 'FinAccInd',
                'active'      => request()->routeIs('finances.accounts.*'),
            ];

            $items[] = [
                'label'       => 'Métodos de pago',
                'description' => 'Efectivo, tarjeta, SPEI y otros — cada uno ligado a su cuenta contable.',
                'route'       => route('finances.payment-methods.index'),
                'debug_id'    => 'FinPmInd',
                'active'      => request()->routeIs('finances.payment-methods.*'),
            ];

            $items[] = [
                'label'       => 'Series de documentos',
                'description' => 'Foliado de recibos, facturas y notas por sucursal o global.',
                'route'       => route('finances.document-series.index'),
                'debug_id'    => 'FinDsInd',
                'active'      => request()->routeIs('finances.document-series.*'),
            ];

            $items[] = [
                'label'       => 'Cajas',
                'description' => 'Registro de cajas físicas por sucursal para apertura y corte diario.',
                'route'       => route('finances.cash-registers.index'),
                'debug_id'    => 'FinCrInd',
                'active'      => request()->routeIs('finances.cash-registers.*'),
            ];
        }

        return [
            'label'  => 'Finanzas',
            'active' => request()->routeIs('finances.*'),
            'items'  => $items,
        ];
    }
}
