<?php

namespace App\Support\WhatsApp;

class WhatsAppLink
{
    /**
     * Link "click to chat" de WhatsApp, con mensaje prellenado opcional.
     *
     * Usa `api.whatsapp.com/send` y NO `wa.me`: la redirección de `wa.me` (que el navegador de
     * escritorio sí sigue, a diferencia del teléfono, donde el sistema abre la app directo)
     * reemplaza cada emoji de 4 bytes por U+FFFD (�) — verificado en vivo el 27/09/2026:
     * `wa.me/…?text=%F0%9F%90%B6` redirige a `…text=%EF%BF%BD`. `api.whatsapp.com/send`
     * responde 200 sin redirigir y conserva el texto intacto.
     *
     * @param  string|null  $number  En formato de `PhoneNormalizer::toWhatsAppNumber()`; null = sin
     *                               destinatario (el usuario elige el chat, p. ej. compartir dirección).
     */
    public static function to(?string $number, string $message = ''): string
    {
        $params = array_filter([
            'phone' => $number ?: null,
            'text' => $message !== '' ? $message : null,
        ]);

        return 'https://api.whatsapp.com/send'.($params ? '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
    }
}
