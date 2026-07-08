<?php

namespace App\Support\Pages;

class MapaZonasPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Mapa y cobertura', 'current' => true]],
            static::header('Operación', 'Mapa y cobertura espacial', 'Visualiza sucursales, clientes, mascotas y vehículos de reparto en un mapa para navegar ideas de cobertura.'),
            'AX-MAPZN',
        );
    }
}
