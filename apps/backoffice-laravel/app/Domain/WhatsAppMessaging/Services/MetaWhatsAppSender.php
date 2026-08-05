<?php

namespace App\Domain\WhatsAppMessaging\Services;

use App\Domain\WhatsAppMessaging\Contracts\WhatsAppSenderInterface;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Support\Facades\Log;

/**
 * Envía mensajes de plantilla vía WhatsApp Business Platform (Graph API de Meta,
 * Cloud API — POST /{phone_number_id}/messages). Mismo proveedor y mismo patrón
 * de credenciales que MetaCatalogSyncService (BL-052), sección `whatsapp_messaging`
 * de SystemSettings en vez de `whatsapp_catalog`.
 *
 * STUB a propósito: hasta que exista whatsapp_messaging_access_token real (pendiente
 * del trámite en Meta Business Manager, ver BL-024b), sendTemplate() se limita a
 * validar configuración y salir con status=skipped sin llamar a la API ni lanzar
 * excepción — así whatsapp:enviar-recordatorios-cita puede correr por Schedule::
 * en producción desde ya, sin romper nada, mientras se completa el trámite.
 * TODO: una vez haya token, reemplazar el bloque marcado abajo por la llamada real
 * (mismo molde que MetaCatalogSyncService::sync() — Http::withToken(...)->post(...)).
 */
class MetaWhatsAppSender implements WhatsAppSenderInterface
{
    private const GRAPH_VERSION = 'v21.0';

    public function __construct(private SystemSettings $settings) {}

    public function sendTemplate(string $to, string $templateName, string $languageCode, array $parameters): array
    {
        $all = $this->settings->all();

        if (! $all['whatsapp_messaging_enabled']) {
            return ['status' => 'skipped', 'provider_message_id' => null, 'error' => 'whatsapp_messaging_enabled está apagado'];
        }

        $phoneNumberId = $all['whatsapp_messaging_phone_number_id'] ?? null;
        $accessToken = $all['whatsapp_messaging_access_token'] ?? null;

        if (! $phoneNumberId || ! $accessToken) {
            Log::warning('WhatsApp: intento de envío sin credenciales configuradas (whatsapp_messaging_phone_number_id / whatsapp_messaging_access_token).');

            return ['status' => 'skipped', 'provider_message_id' => null, 'error' => 'Faltan credenciales de WhatsApp Business en Configuración del sistema.'];
        }

        // TODO (pendiente del token real, ver BL-024b): reemplazar por la llamada real.
        // $response = Http::withToken($accessToken)->post(
        //     'https://graph.facebook.com/'.self::GRAPH_VERSION."/{$phoneNumberId}/messages",
        //     [
        //         'messaging_product' => 'whatsapp',
        //         'to' => $to,
        //         'type' => 'template',
        //         'template' => [
        //             'name' => $templateName,
        //             'language' => ['code' => $languageCode],
        //             'components' => [[
        //                 'type' => 'body',
        //                 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $parameters),
        //             ]],
        //         ],
        //     ]
        // );
        //
        // if ($response->failed()) {
        //     $reason = $response->json('error.message') ?? $response->body();
        //     Log::error('Fallo al enviar plantilla de WhatsApp', ['to' => $to, 'status' => $response->status(), 'body' => $response->body()]);
        //     return ['status' => 'failed', 'provider_message_id' => null, 'error' => $reason];
        // }
        //
        // return ['status' => 'sent', 'provider_message_id' => $response->json('messages.0.id'), 'error' => null];

        Log::info('WhatsApp: envío real todavía no implementado (stub) — no se mandó nada.', ['to' => $to, 'template' => $templateName]);

        return ['status' => 'skipped', 'provider_message_id' => null, 'error' => 'Envío real todavía no implementado (stub, pendiente de credenciales Meta).'];
    }
}
