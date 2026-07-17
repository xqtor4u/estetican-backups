<?php

namespace App\Support\Navigation\Groups;

use App\Support\SystemSettings\SystemSettings;

class InventoryNavigation
{
    /**
     * @return array<string, mixed>
     */
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        $moduleEnabled = (bool) app(SystemSettings::class)->all()['store_module_enabled'];

        if ($moduleEnabled && ($user?->can('ver catalogo_articulos') || $user?->is_super_admin)) {
            $items[] = [
                'label' => 'Artículos',
                'description' => 'Maestro de productos de venta (marca, presentación, precio, existencia) — fundación del futuro módulo de inventario transaccional.',
                'route' => route('items.index'),
                'active' => request()->routeIs('items.*'),
            ];
        }

        return [
            'label' => 'Inventario',
            'active' => request()->routeIs('items.*'),
            'items' => $items,
        ];
    }
}
