<?php

namespace App\Support\Pages;

use App\Models\Client;

class ClientsPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Clientes', 'current' => true]],
            static::header('Operacion comercial', 'Clientes', 'Listado general de clientes con acceso directo a sus datos, mascotas vivas y acciones principales.'),
            'CliInd',
        );
    }

    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Clientes', 'url' => route('clients.index')], ['label' => 'Nuevo cliente', 'current' => true]],
            static::header('Alta operativa', 'Crear cliente', 'Captura el cliente y, dentro del mismo flujo, agrega direcciones, teléfonos y mascotas iniciales.'),
            'CliCre',
        );
    }

    public static function show(Client $client): array
    {
        $name = trim($client->first_name.' '.$client->last_name);

        return static::page(
            [static::home(), ['label' => 'Clientes', 'url' => route('clients.index')], ['label' => $name, 'current' => true]],
            static::header('Detalle de cliente', $name, 'Vista resumida del cliente con direcciones, teléfonos y mascotas vivas relacionadas.'),
            'CliSho',
        );
    }

    public static function edit(Client $client): array
    {
        $name = trim($client->first_name.' '.$client->last_name);

        return static::page(
            [static::home(), ['label' => 'Clientes', 'url' => route('clients.index')], ['label' => $name, 'url' => route('clients.show', $client)], ['label' => 'Editar cliente', 'current' => true]],
            static::header('Edicion', 'Editar cliente', 'Actualiza la ficha general, las relaciones principales y el acceso a dependencias por mascota.'),
            'CliEdi',
        );
    }
}