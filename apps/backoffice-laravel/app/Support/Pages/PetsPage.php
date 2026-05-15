<?php

namespace App\Support\Pages;

use App\Models\Client;
use App\Models\Pet;

class PetsPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Mascotas', 'current' => true]],
            static::header('Modulo operativo', 'Mascotas', 'Listado raíz del dominio mascota con cambio entre bloques y tabla, sin perder acceso al cliente ni a la gestión especializada.'),
            'PetInd',
        );
    }

    public static function show(Pet $pet, Client $client, bool $isRootView, string $returnViewMode): array
    {
        $breadcrumbs = $isRootView
            ? [
                static::home(),
                ['label' => 'Mascotas', 'url' => route('pets.index', ['view' => $returnViewMode])],
                ['label' => $pet->name, 'current' => true],
            ]
            : [
                static::home(),
                ['label' => 'Clientes', 'url' => route('clients.index')],
                ['label' => trim($client->first_name.' '.$client->last_name), 'url' => route('clients.show', $client)],
                ['label' => $pet->name, 'current' => true],
            ];

        return static::page(
            $breadcrumbs,
            static::header(
                $isRootView ? 'Detalle de mascota' : 'Mascota seleccionada',
                $pet->name,
                $isRootView
                    ? 'Vista raíz del módulo de mascotas con acceso directo al detalle operativo y a las dependencias especializadas.'
                    : 'Desde aquí gestionas las tablas dependientes directas de la mascota dentro del contexto del cliente.'
            ),
            $isRootView ? 'PetSho' : 'CliPetSho',
        );
    }
}