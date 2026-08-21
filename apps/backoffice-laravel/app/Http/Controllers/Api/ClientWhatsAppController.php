<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\PhoneNormalizer;
use App\Support\WhatsApp\TemplateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientWhatsAppController extends Controller
{
    /**
     * Plantillas activas de contexto "cliente" (mensaje directo, sin cita de por medio) o
     * "general" (campaña, oferta de temporada u otro mensaje libre) — las mismas que se
     * administran en Configuración → WhatsApp → Plantillas. Ambos contextos se resuelven solo
     * con datos del cliente (sin cita de por medio), por eso comparten este mismo listado.
     * Incluye `context` para que el frontend sepa cuándo puede hacer falta preguntar la mascota
     * (solo las de contexto "general" pueden usar `{mascota}`).
     */
    public function templates(): JsonResponse
    {
        $templates = WhatsAppTemplate::whereIn('context', ['cliente', 'general'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'context']);

        return response()->json($templates);
    }

    /**
     * Mascotas vivas del cliente — usado para preguntar "¿para cuál mascota?" antes de enviar
     * una plantilla de contexto "general" que use `{mascota}`, cuando el cliente tiene más de
     * una y no se puede adivinar cuál (ver `link()`).
     */
    public function livePets(Client $client): JsonResponse
    {
        $pets = $client->livePets()->orderBy('name')->get(['id', 'name']);

        return response()->json($pets);
    }

    /**
     * Arma el link de wa.me para un teléfono real del cliente — con mensaje vacío ("mensaje
     * directo") o resuelto desde una plantilla de contexto "cliente"/"general" (usa los datos
     * del cliente para preformatear el mensaje).
     */
    public function link(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'template_id' => ['nullable', 'integer'],
            'pet_id' => ['nullable', 'integer'],
        ]);

        $client->loadMissing('phones');

        $belongsToClient = $client->phones->contains(fn ($phone) => $phone->number === $validated['phone']);

        if (! $belongsToClient) {
            return response()->json([
                'message' => 'Ese teléfono no pertenece a este cliente.',
            ], 422);
        }

        $waNumber = PhoneNormalizer::toWhatsAppNumber($validated['phone']);

        if (! $waNumber) {
            return response()->json([
                'message' => 'Ese teléfono no es un número reconocible de WhatsApp (se espera un celular MX de 10 dígitos).',
            ], 422);
        }

        $pet = null;

        if (! empty($validated['pet_id'])) {
            $pet = $client->pets()->find($validated['pet_id']);

            if (! $pet) {
                return response()->json([
                    'message' => 'Esa mascota no pertenece a este cliente.',
                ], 422);
            }
        }

        $message = '';

        if (! empty($validated['template_id'])) {
            $template = WhatsAppTemplate::whereIn('context', ['cliente', 'general'])
                ->where('is_active', true)
                ->find($validated['template_id']);

            if (! $template) {
                return response()->json([
                    'message' => 'La plantilla seleccionada no existe o ya no está activa.',
                ], 404);
            }

            $message = $template->context === 'general'
                ? TemplateResolver::resolveGeneral($template->body, $client, $pet)
                : TemplateResolver::resolveForClient($template->body, $client);
        }

        $waLink = 'https://wa.me/'.$waNumber.($message !== '' ? '?text='.rawurlencode($message) : '');

        return response()->json([
            'wa_link' => $waLink,
            'message' => $message,
        ]);
    }
}
