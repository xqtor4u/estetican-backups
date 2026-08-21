<?php

namespace App\Support\WhatsApp;

use App\Models\Client;

class PhoneNormalizer
{
    /**
     * Convierte un número guardado a formato wa.me (código de país + 10 dígitos, sin '+').
     * En producción los números están capturados de formas distintas: 10 dígitos sin lada,
     * con '+52' ya incluido, o con el viejo prefijo '521' de WhatsApp para móviles MX.
     * Cualquier otro largo se considera no reconocible y no se envía.
     */
    public static function toWhatsAppNumber(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        return match (true) {
            strlen($digits) === 10 => '52'.$digits,
            strlen($digits) === 12 && str_starts_with($digits, '52') => $digits,
            strlen($digits) === 13 && str_starts_with($digits, '521') => '52'.substr($digits, 3),
            default => null,
        };
    }

    /**
     * Elige el teléfono más apropiado del cliente: prefiere type=mobile (WhatsApp/SMS solo
     * funcionan con celular) según el orden de importancia capturado (Client::phones() ya
     * viene ordenado por sort_order); si no hay ningún móvil, usa el de mayor importancia
     * de cualquier tipo.
     */
    public static function bestPhoneFor(Client $client): ?string
    {
        $phones = $client->phones;

        $mobile = $phones->firstWhere('type', 'mobile');

        return ($mobile ?? $phones->first())?->number;
    }
}
