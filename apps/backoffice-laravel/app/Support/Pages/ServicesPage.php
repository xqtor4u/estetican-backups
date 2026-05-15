<?php

namespace App\Support\Pages;

use App\Models\Service;

class ServicesPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Servicios', 'current' => true]],
            static::header('Catálogo operativo', 'Servicios', 'Base comercial del negocio para estandarizar descripción, precio sugerido y duración antes de planear o ejecutar trabajos.'),
            'SerInd',
        );
    }

    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Servicios', 'url' => route('services.index')], ['label' => 'Nuevo servicio', 'current' => true]],
            static::header('Alta de catálogo', 'Crear servicio', 'Define la plantilla comercial base del servicio. La ejecución podrá ajustar descripción y precio sin perder histórico.'),
            'SerCre',
        );
    }

    public static function show(Service $service): array
    {
        return static::page(
            [static::home(), ['label' => 'Servicios', 'url' => route('services.index')], ['label' => $service->name, 'current' => true]],
            static::header('Detalle de catálogo', $service->name, 'Vista resumida del servicio base que alimenta agenda, ejecución y precios sugeridos.'),
            'SerSho',
        );
    }

    public static function edit(Service $service): array
    {
        return static::page(
            [static::home(), ['label' => 'Servicios', 'url' => route('services.index')], ['label' => $service->name, 'url' => route('services.show', $service)], ['label' => 'Editar', 'current' => true]],
            static::header('Mantenimiento de catálogo', 'Editar servicio', 'Ajusta la definición base del servicio sin comprometer el histórico ejecutado ya congelado.'),
            'SerEdi',
        );
    }
}