<?php

namespace App\Support\Pages;

class SystemSettingsPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Configuración del sistema', 'current' => true]],
            static::header('Gobierno operativo', 'Configuración del sistema', 'Centraliza visualización, convenciones de runtime y controles ligeros de seguridad sin mezclar estos ajustes con catálogos de negocio.'),
            'SysSetInd',
        );
    }
}