<?php

namespace App\Support\WhatsApp;

use App\Models\Client;

class PhoneNormalizer
{
    /**
     * Convierte un número guardado (10 dígitos MX, sin lada) al formato que requiere wa.me.
     * Cualquier longitud distinta a 10 dígitos se considera no reconocible como MX y no se envía.
     */
    public static function toWhatsAppNumber(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        return strlen($digits) === 10 ? '52'.$digits : null;
    }

    /**
     * Elige el teléfono más apropiado del cliente: prefiere type=mobile, si no hay usa el primero.
     */
    public static function bestPhoneFor(Client $client): ?string
    {
        $phones = $client->phones;

        $mobile = $phones->firstWhere('type', 'mobile');

        return ($mobile ?? $phones->first())?->number;
    }
}
