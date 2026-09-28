<?php

namespace App\Support\WhatsApp;

use App\Models\Branch;
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
        // "calendario" lo escribe el sync automático de Google Calendar: no hay quien envíe
        // (sin {usuario}/{sucursal}) y la hora de envío no significa nada ahí.
        if ($context === 'calendario') {
            return self::contextVariables($context);
        }

        $variables = self::contextVariables($context) + self::SYSTEM_VARIABLES;

        return $context === 'cita' ? $variables + self::PRICE_VARIABLES : $variables;
    }

    /** Variables del sistema: salen de quien envía y del momento del envío, no del cliente. */
    private const SYSTEM_VARIABLES = [
        'hora_local' => 'Hora actual del negocio al momento de enviar',
        'sucursal' => 'Sucursal de quien envía el mensaje',
        'usuario' => 'Nombre de quien envía el mensaje',
    ];

    /** Solo hay precios cuando hay una cita de por medio. */
    private const PRICE_VARIABLES = [
        'precio_cita' => 'Total de la cita (el del presupuesto aceptado, si lo hay)',
        'precio_lista' => 'Suma de los precios de catálogo de los servicios de la cita',
    ];

    /** @return array<string, string> */
    private static function contextVariables(string $context): array
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

        if ($context === 'calendario') {
            return [
                'cliente' => 'Nombre del cliente',
                'mascota' => 'Nombre de la mascota',
                'servicio' => 'Servicio(s) agendado(s)',
                'fecha' => 'Fecha de la cita',
                'hora' => 'Hora de la cita',
                'operador' => 'Nombre del operador asignado',
                'folio' => 'Folio de la orden (vacío si la cita no tiene folio)',
                'notas' => 'Notas internas de la cita (vacío si no tiene)',
                'telefono' => 'Teléfono del cliente (vacío si no tiene teléfono registrado)',
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
            '{precio_cita}' => self::money($booking->chargesTotal()),
            '{precio_lista}' => self::money((float) $booking->services->sum(fn ($line) => (float) ($line->service?->price ?? 0))),
        ];

        // B1: desde una cita, {sucursal} es la de la cita; sin sucursal, la de quien envía.
        return strtr($body, $replacements + self::systemReplacements($timeFormat, $booking->branch?->name));
    }

    /**
     * Resuelve una plantilla de contexto "calendario" (descripción del evento de Google
     * Calendar) — separado de `resolve()` a propósito: expone `{operador}`, `{folio}`,
     * `{notas}` y `{telefono}`, datos internos que no deben colarse a una plantilla
     * cliente-facing de WhatsApp/email por reusar el mismo método.
     */
    public static function resolveForCalendarEvent(string $body, SpaBooking $booking, ?string $dateFormat = null, ?string $timeFormat = null): string
    {
        $dateFormat ??= (string) config('backoffice.system.date_format', 'd/m/Y');
        $timeFormat ??= config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A';

        $client = $booking->pet?->client;

        $replacements = [
            '{cliente}' => $client?->full_name ?: 'Cliente',
            '{mascota}' => $booking->pet?->name ?: 'Mascota',
            '{servicio}' => $booking->services->pluck('service.name')->filter()->implode(', '),
            '{fecha}' => $booking->scheduled_at?->format($dateFormat) ?? '',
            '{hora}' => $booking->scheduled_at?->format($timeFormat) ?? '',
            '{operador}' => $booking->operator?->full_name ?: '',
            '{folio}' => (string) ($booking->order_folio ?? ''),
            '{notas}' => (string) ($booking->notes ?? ''),
            '{telefono}' => $client ? ((string) (PhoneNormalizer::bestPhoneFor($client) ?? '')) : '',
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

        return strtr($body, $replacements + self::systemReplacements());
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

        return strtr($body, $replacements + self::systemReplacements());
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

        return strtr($body, $replacements + self::systemReplacements());
    }

    /**
     * `{hora_local}`/`{sucursal}`/`{usuario}` — quien envía y cuándo. Todos los envíos de
     * WhatsApp/correo que usan estas plantillas los dispara una persona (bandeja, recurrencias,
     * selector de la ficha o de la cita); sin usuario autenticado quedan en blanco, nunca como
     * texto literal. La hora usa la zona del negocio (`system_timezone`, aplicada al arrancar,
     * ver `AppServiceProvider`).
     *
     * @return array<string, string>
     */
    private static function systemReplacements(?string $timeFormat = null, ?string $branchName = null): array
    {
        $timeFormat ??= config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A';
        $user = auth()->user();

        return [
            '{hora_local}' => now()->format($timeFormat),
            '{sucursal}' => $branchName ?: self::senderBranchName($user),
            '{usuario}' => $user ? (trim((string) ($user->first_name ?? '')) ?: (string) $user->name) : '',
        ];
    }

    /** Sucursal del usuario que envía; si no tiene una, la única sucursal activa (si solo hay una). */
    private static function senderBranchName(mixed $user): string
    {
        $branch = $user?->branch;

        if (! $branch) {
            $active = Branch::where('is_active', true)->limit(2)->get(['id', 'name']);
            $branch = $active->count() === 1 ? $active->first() : null;
        }

        return (string) ($branch?->name ?? '');
    }

    private static function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
