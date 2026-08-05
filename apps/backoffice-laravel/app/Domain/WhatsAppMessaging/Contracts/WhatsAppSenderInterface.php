<?php

namespace App\Domain\WhatsAppMessaging\Contracts;

interface WhatsAppSenderInterface
{
    /**
     * Envía un mensaje de plantilla aprobada por Meta a un número de WhatsApp.
     *
     * @param string $to Número en formato wa.me (52 + 10 dígitos, sin '+').
     * @param string $templateName Nombre exacto de la plantilla aprobada en Meta.
     * @param string $languageCode Código de idioma de la plantilla (ej. 'es_MX').
     * @param array<int, string> $parameters Parámetros posicionales para las variables numeradas de la plantilla ({{1}}, {{2}}, ...).
     * @return array{status: 'sent'|'skipped'|'failed', provider_message_id: ?string, error: ?string}
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode, array $parameters): array;
}
