<?php

namespace App\Support\WhatsApp;

use App\Models\SpaBooking;

class TemplateResolver
{
    /**
     * @return array<string, string> variable => descripción, para mostrar en el editor de plantillas
     */
    public static function availableVariables(): array
    {
        return [
            'cliente' => 'Nombre del cliente',
            'mascota' => 'Nombre de la mascota',
            'servicio' => 'Servicio(s) agendado(s)',
            'fecha' => 'Fecha de la cita',
            'hora' => 'Hora de la cita',
        ];
    }

    public static function resolve(string $body, SpaBooking $booking, ?string $dateFormat = null, ?string $timeFormat = null): string
    {
        $dateFormat ??= (string) config('backoffice.system.date_format', 'd/m/Y');
        $timeFormat ??= config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A';

        $client = $booking->pet?->client;

        $replacements = [
            '{cliente}' => $client?->full_name ?: 'Cliente',
            '{mascota}' => $booking->pet?->name ?: 'tu mascota',
            '{servicio}' => $booking->services->pluck('service.name')->filter()->implode(', ') ?: 'servicio agendado',
            '{fecha}' => $booking->scheduled_at?->format($dateFormat) ?? '',
            '{hora}' => $booking->scheduled_at?->format($timeFormat) ?? '',
        ];

        return strtr($body, $replacements);
    }
}
