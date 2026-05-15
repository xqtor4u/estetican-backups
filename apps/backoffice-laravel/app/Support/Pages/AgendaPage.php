<?php

namespace App\Support\Pages;

use App\Models\Client;
use App\Models\Pet;
use App\Models\SpaBooking;

class AgendaPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Agenda', 'current' => true]],
            static::header('Programación operativa', 'Agenda Universal', 'Citas de SPA y estancias de Hotel con lectura diaria por horario y disponibilidad operativa.'),
            'AgUniInd',
        );
    }

    public static function create(?Pet $pet = null, ?Client $client = null, bool $isRootView = true, string $returnViewMode = 'index'): array
    {
        $breadcrumbs = [static::home()];

        if ($pet && $isRootView) {
            $breadcrumbs[] = ['label' => 'Mascotas', 'url' => route('pets.index', ['view' => $returnViewMode])];
            $breadcrumbs[] = ['label' => $pet->name, 'url' => route('pets.show', ['pet' => $pet, 'view' => $returnViewMode])];
        } elseif ($pet && $client) {
            $breadcrumbs[] = ['label' => 'Clientes', 'url' => route('clients.index')];
            $breadcrumbs[] = ['label' => trim($client->first_name . ' ' . $client->last_name), 'url' => route('clients.show', $client)];
            $breadcrumbs[] = ['label' => $pet->name, 'url' => route('clients.pets.show', [$client, $pet])];
        } else {
            $breadcrumbs[] = ['label' => 'Agenda', 'url' => route('agenda.index')];
        }

        $breadcrumbs[] = ['label' => 'Programar servicio', 'current' => true];

        return static::page(
            $breadcrumbs,
            static::header('Nueva Reservación', 'Programar servicio', ''),
            'AgSpaCre',
        );
    }

    public static function show(SpaBooking $booking): array
    {
        $pet = $booking->pet;
        $client = $pet?->client;

        return static::page(
            [
                static::home(),
                ['label' => 'Agenda', 'url' => route('agenda.index')],
                ['label' => $pet?->name ?: 'Mascota', 'current' => true],
            ],
            static::header('Agenda SPA', 'Detalle de booking', 'Lectura operativa del booking con contexto de mascota, cliente, servicios y acciones de seguimiento.'),
            'AgSpaSho',
        );
    }

    public static function edit(SpaBooking $booking): array
    {
        $pet = $booking->pet;

        return static::page(
            [
                static::home(),
                ['label' => 'Agenda', 'url' => route('agenda.index')],
                ['label' => $pet?->name ?: 'Mascota', 'url' => route('agenda.show', $booking)],
                ['label' => 'Reprogramar', 'current' => true],
            ],
            static::header('Agenda SPA', 'Reprogramar booking', 'Ajusta fecha, hora y notas operativas sin perder el snapshot de servicios ya congelado.'),
            'AgSpaEdi',
        );
    }
}