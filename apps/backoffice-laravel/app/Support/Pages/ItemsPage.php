<?php

namespace App\Support\Pages;

use App\Models\Item;

class ItemsPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Artículos', 'current' => true]],
            static::header('Catálogo canónico', 'Artículos', 'Maestro de productos (marca, presentación, departamento) — fundación del futuro módulo de inventario, sin existencias todavía.'),
            'ArtInd',
        );
    }

    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Artículos', 'url' => route('items.index')], ['label' => 'Nuevo artículo', 'current' => true]],
            static::header('Alta de catálogo', 'Crear artículo', 'Identidad del producto: nombre, marca y presentación. Sin cantidades ni almacén todavía.'),
            'ArtCre',
        );
    }

    public static function edit(Item $item): array
    {
        return static::page(
            [static::home(), ['label' => 'Artículos', 'url' => route('items.index')], ['label' => $item->name, 'current' => true]],
            static::header('Mantenimiento de catálogo', 'Editar artículo', 'Ajusta la identidad del producto sin afectar las vacunas u otros registros que ya lo referencian.'),
            'ArtEdi',
        );
    }
}
