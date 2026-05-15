<?php

namespace App\Support\Pages;

use App\Models\Resource;

class ResourcesPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Recursos y activos', 'current' => true]],
            static::header('Catálogo operativo', 'Recursos y activos', 'Jaulas y otros activos físicos controlados por sucursal para sostener disponibilidad, bloqueos y uso compartido.'),
            'ResInd',
        );
    }

    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Recursos y activos', 'url' => route('resources.index')], ['label' => 'Nuevo recurso', 'current' => true]],
            static::header('Alta operativa', 'Crear recurso', 'Registra una jaula u otro activo físico antes de conectarlo con agenda, hotel o bloqueos operativos.'),
            'ResCre',
        );
    }

    public static function show(Resource $resource): array
    {
        return static::page(
            [static::home(), ['label' => 'Recursos y activos', 'url' => route('resources.index')], ['label' => $resource->name, 'current' => true]],
            static::header('Detalle operativo', $resource->name, 'Ficha base del activo físico con su sucursal, estado y trazabilidad reciente de asignaciones.'),
            'ResSho',
        );
    }

    public static function edit(Resource $resource): array
    {
        return static::page(
            [static::home(), ['label' => 'Recursos y activos', 'url' => route('resources.index')], ['label' => $resource->name, 'url' => route('resources.show', $resource)], ['label' => 'Editar', 'current' => true]],
            static::header('Mantenimiento operativo', 'Editar recurso', 'Ajusta la definición del activo sin perder su papel como unidad física real dentro de la operación.'),
            'ResEdi',
        );
    }
}