<?php

namespace App\Support\Navigation\Groups;

use App\Support\SystemSettings\SystemSettings;

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

        $storeModuleEnabled = (bool) app(SystemSettings::class)->all()['store_module_enabled'];

        if ($storeModuleEnabled && ($user?->can('ver catalogo_grupos') || $user?->is_super_admin)) {
            $items[] = [
                'label' => 'Grupos',
                'description' => 'Combos de Servicios + Artículos con cantidad — un clic los agrega todos a una cotización, facturados desglosados.',
                'route' => route('groups.index'),
                'active' => request()->routeIs('groups.*'),
            ];
        }

        return [
            'label' => 'Catálogos',
            'active' => request()->routeIs('services.*', 'groups.*'),
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
        ];
    }
}