<?php

namespace App\Support\Pages;

use App\Models\HotelReservation;

class HotelReservationsPage extends BasePage
{
    public static function index(): array
    {
        return static::page(
            [static::home(), ['label' => 'Hotel', 'current' => true]],
            static::header('Agenda Hotel', 'Reservas de hotel', 'Estancias nocturnas, guarderías y cuidados prolongados agendados por mascota.'),
            'AgHotInd',
        );
    }

    public static function create(): array
    {
        return static::page(
            [static::home(), ['label' => 'Hotel', 'url' => route('hotel-reservations.index')], ['label' => 'Nueva reserva', 'current' => true]],
            static::header('Nueva estancia', 'Reservar hotel', 'Bloquea la jaula y registra el rango de estancia para la mascota seleccionada.'),
            'AgHotNew',
        );
    }

    public static function show(HotelReservation $reservation): array
    {
        $petName = $reservation->pet?->name ?? 'Reserva';

        return static::page(
            [static::home(), ['label' => 'Hotel', 'url' => route('hotel-reservations.index')], ['label' => $petName, 'current' => true]],
            static::header('Detalle de estancia', $petName, 'Reserva planeada con control de jaula bloqueada y estado operativo.'),
            'AgHotSho',
        );
    }

    public static function edit(HotelReservation $reservation): array
    {
        $petName = $reservation->pet?->name ?? 'Reserva';

        return static::page(
            [static::home(), ['label' => 'Hotel', 'url' => route('hotel-reservations.index')], ['label' => $petName, 'url' => route('hotel-reservations.show', $reservation)], ['label' => 'Editar', 'current' => true]],
            static::header('Editar estancia', $petName, 'Ajusta las fechas, mascota o jaula asignada sin perder el contexto operativo.'),
            'AgHotEdi',
        );
    }
}
