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
                'label'       => 'Expediente clínico',
                'description' => 'Visitas SOAP, alergias, condiciones crónicas y vacunas por mascota.',
                'route'       => route('clinical.index'),
                'debug_id'    => 'CliInd',
                'active'      => request()->routeIs('clinical.*'),
            ];
        }

        return [
            'label'  => 'Veterinaria',
            'active' => request()->routeIs('clinical.*'),
            'items'  => $items,
        ];
    }
}
