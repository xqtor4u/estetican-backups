<?php

namespace App\Support\Navigation\Groups;

use App\Support\SystemSettings\SystemSettings;

class VeterinariaNavigation
{
    public static function group(): array
    {
        $user = auth()->user();
        $items = [];

        $moduleEnabled = (bool) app(SystemSettings::class)->all()['clinical_module_enabled'];

        if ($moduleEnabled && ($user?->can('ver clinico') || $user?->is_super_admin)) {
            $items[] = [
                'label' => 'Expediente clínico',
                'description' => 'Visitas SOAP, alergias, condiciones crónicas y vacunas por mascota.',
                'route' => route('clinical.index'),
                'debug_id' => 'CliInd',
                'active' => request()->routeIs('clinical.*'),
            ];
        }

        // Farmacia nunca fue una tabla aparte — son artículos del catálogo general
        // (`items`, department = 'Farmacia', ver BL-071) con la misma pantalla que usa
        // Tienda. Accesible aquí aunque Tienda esté apagada (EnsureStoreOrClinicalModuleEnabled).
        if ($moduleEnabled && ($user?->can('ver catalogo_articulos') || $user?->is_super_admin)) {
            $items[] = [
                'label' => 'Farmacia',
                'description' => 'Medicamentos del catálogo general de artículos (departamento "Farmacia").',
                'route' => route('items.index', ['search' => 'Farmacia']),
                'debug_id' => 'CliFar',
                'active' => request()->routeIs('items.*') && request()->query('search') === 'Farmacia',
            ];
        }

        return [
            'label' => 'Veterinaria',
            'active' => request()->routeIs('clinical.*'),
            'items' => $items,
        ];
    }
}
