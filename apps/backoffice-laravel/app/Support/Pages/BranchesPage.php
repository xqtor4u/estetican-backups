<?php

namespace App\Support\Pages;

use App\Models\Branch;

class BranchesPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Sucursales', 'current' => true]],
            static::header('Catálogo operativo', 'Sucursales', 'Bases operativas controladas para sostener asignación de personal, recursos y futura disponibilidad multisucursal.'),
            'BraInd',
        );
    }

    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Sucursales', 'url' => route('branches.index')], ['label' => 'Nueva sucursal', 'current' => true]],
            static::header('Alta operativa', 'Crear sucursal', 'Registra una base operativa controlada antes de asignarla a operadores, recursos o disponibilidad futura.'),
            'BraCre',
        );
    }

    public static function show(Branch $branch): array
    {
        return static::page(
            [static::home(), ['label' => 'Sucursales', 'url' => route('branches.index')], ['label' => $branch->name, 'current' => true]],
            static::header('Detalle operativo', $branch->name, 'Ficha base de la sucursal para asignaciones presentes y expansión futura de recursos y disponibilidad.'),
            'BraSho',
        );
    }

    public static function edit(Branch $branch): array
    {
        return static::page(
            [static::home(), ['label' => 'Sucursales', 'url' => route('branches.index')], ['label' => $branch->name, 'url' => route('branches.show', $branch)], ['label' => 'Editar', 'current' => true]],
            static::header('Mantenimiento operativo', 'Editar sucursal', 'Ajusta la base operativa sin volver a mezclar esta capa con el alta directa de operadores.'),
            'BraEdi',
        );
    }
}