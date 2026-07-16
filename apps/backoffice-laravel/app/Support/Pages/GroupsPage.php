<?php

namespace App\Support\Pages;

use App\Models\Group;

class GroupsPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Grupos', 'current' => true]],
            static::header('Catálogo comercial', 'Grupos', 'Combos de Servicios + Artículos con cantidad — un clic los agrega todos a una cotización, facturados desglosados.'),
            'GrpInd',
        );
    }

    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Grupos', 'url' => route('groups.index')], ['label' => 'Nuevo grupo', 'current' => true]],
            static::header('Alta de catálogo', 'Crear grupo', 'Nombre y datos base — los componentes se agregan después de guardar.'),
            'GrpCre',
        );
    }

    public static function edit(Group $group): array
    {
        return static::page(
            [static::home(), ['label' => 'Grupos', 'url' => route('groups.index')], ['label' => $group->name, 'current' => true]],
            static::header('Mantenimiento de catálogo', 'Editar grupo', 'Ajusta los datos base y administra los componentes (Servicios/Artículos) que lo forman.'),
            'GrpEdi',
        );
    }
}
