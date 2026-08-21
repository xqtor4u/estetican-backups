<?php

namespace App\Support\WhatsApp;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use Carbon\CarbonInterface;

class TemplateResolver
{
    /**
     * @return array<string, string> variable => descripción, para mostrar en el editor de plantillas
     */
    public static function availableVariables(string $context = 'cita'): array
    {
        if ($context === 'recurrencia') {
            return [
                'cliente' => 'Nombre del cliente',
                'mascota' => 'Nombre de la mascota',
                'servicio' => 'Servicio recurrente (ej. Baño)',
                'ultima_fecha' => 'Fecha del último servicio realizado',
                'dias_vencido' => 'Días transcurridos desde que se cumplió el ciclo de recurrencia',
            ];
        }

        if ($context === 'cliente') {
            return [
                'cliente' => 'Nombre del cliente',
            ];
        }

        if ($context === 'general') {
            return [
                'cliente' => 'Nombre del cliente',
                'mascota' => 'Nombre de la mascota (solo se rellena si el cliente tiene una única mascota viva; si no, queda en blanco)',
                'servicio' => 'Servicio — no se rellena al enviar desde la ficha del cliente, queda en blanco',
                'fecha' => 'Fecha — no se rellena al enviar desde la ficha del cliente, queda en blanco',
                'hora' => 'Hora — no se rellena al enviar desde la ficha del cliente, queda en blanco',
                'ultima_fecha' => 'Fecha del último servicio — no se rellena al enviar desde la ficha del cliente, queda en blanco',
                'dias_vencido' => 'Días de vencimiento — no se rellena al enviar desde la ficha del cliente, queda en blanco',
            ];
        }

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

    public static function resolveForRecurrence(
        string $body,
        Pet $pet,
        Service $service,
        CarbonInterface $lastServiceAt,
        int $daysOverdue,
        ?string $dateFormat = null,
    ): string {
        $dateFormat ??= (string) config('backoffice.system.date_format', 'd/m/Y');

        $client = $pet->client;

        $replacements = [
            '{cliente}' => $client?->full_name ?: 'Cliente',
            '{mascota}' => $pet->name ?: 'tu mascota',
            '{servicio}' => $service->name,
            '{ultima_fecha}' => $lastServiceAt->format($dateFormat),
            '{dias_vencido}' => (string) max($daysOverdue, 0),
        ];

        return strtr($body, $replacements);
    }

    /**
     * Resuelve una plantilla de contexto "cliente" (mensaje directo, sin cita/servicio de por
     * medio) — solo `{cliente}` está disponible en este contexto, ver `availableVariables()`.
     */
    public static function resolveForClient(string $body, Client $client): string
    {
        $replacements = [
            '{cliente}' => $client->full_name ?: 'Cliente',
        ];

        return strtr($body, $replacements);
    }

    /**
     * Resuelve una plantilla de contexto "general" (campaña, oferta de temporada, u otro
     * mensaje libre) enviada desde la ficha del cliente o desde una cita — sin depender de una
     * cita real de por medio (por eso no usa `resolve()`). `{cliente}` siempre se rellena.
     * `{mascota}`: si se pasa `$pet` explícito (ej. la mascota de la cita desde donde se envía,
     * o la que eligió el usuario cuando el cliente tiene varias) se usa esa; si no, se intenta
     * adivinar solo cuando es inequívoco (el cliente tiene una única mascota viva) — con cero o
     * varias mascotas sin `$pet` explícito, queda en blanco en vez de adivinar mal. El resto de
     * las variables de `availableVariables('general')` no tiene de dónde salir en este flujo y
     * queda en blanco, nunca como texto literal `{variable}` visible para el cliente.
     */
    public static function resolveGeneral(string $body, Client $client, ?Pet $pet = null): string
    {
        if (! $pet) {
            $livePets = $client->livePets;
            $pet = $livePets->count() === 1 ? $livePets->first() : null;
        }

        $replacements = [
            '{cliente}' => $client->full_name ?: 'Cliente',
            '{mascota}' => $pet?->name ?: '',
            '{servicio}' => '',
            '{fecha}' => '',
            '{hora}' => '',
            '{ultima_fecha}' => '',
            '{dias_vencido}' => '',
        ];

        return strtr($body, $replacements);
    }
}
