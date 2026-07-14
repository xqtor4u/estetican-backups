<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceAiChat;
use App\Support\Assistant\ServiceCatalogPromptBuilder;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssistantChatController extends Controller
{
    private const MAX_MESSAGES_PER_SESSION = 30;

    private const HISTORY_MESSAGES = 10;

    public function config(SystemSettings $settings): JsonResponse
    {
        $all = $settings->all();

        return response()->json([
            'enabled' => (bool) $all['ai_assistant_enabled'],
            'cta_label' => (string) $all['ai_assistant_cta_label'],
            'cta_url' => (string) $all['ai_assistant_cta_url'],
        ]);
    }

    public function send(Request $request, SystemSettings $settings, ServiceCatalogPromptBuilder $promptBuilder): JsonResponse
    {
        $validated = $request->validate([
            'session_uuid' => ['required', 'string', 'max:64'],
            'message' => ['required', 'string', 'max:500'],
        ]);

        $all = $settings->all();
        $apiKey = $all['ai_assistant_api_key'];

        if (! $apiKey) {
            return response()->json(['message' => 'El asistente no está configurado todavía.'], 503);
        }

        $chat = ServiceAiChat::firstOrCreate(['session_uuid' => $validated['session_uuid']]);

        if ($chat->message_count >= self::MAX_MESSAGES_PER_SESSION) {
            return response()->json([
                'reply' => 'Esta conversación llegó a su límite. Contáctanos directo para seguir platicando.',
                'limit_reached' => true,
            ]);
        }

        $history = $chat->messages()
            ->latest('created_at')
            ->limit(self::HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->map(fn ($message) => ['role' => $message->role, 'content' => $message->content])
            ->values()
            ->all();

        $history[] = ['role' => 'user', 'content' => $validated['message']];

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $all['ai_assistant_model'],
            'max_tokens' => 500,
            'system' => $promptBuilder->build(),
            'messages' => $history,
        ]);

        if ($response->failed()) {
            Log::error('Fallo llamada al asistente de IA', ['status' => $response->status(), 'body' => $response->body()]);

            return response()->json(['message' => 'No pudimos procesar tu mensaje, intenta de nuevo en un momento.'], 502);
        }

        $reply = collect($response->json('content'))
            ->where('type', 'text')
            ->pluck('text')
            ->join("\n");

        if ($reply === '') {
            Log::error('Respuesta del asistente de IA sin contenido de texto', ['body' => $response->body()]);

            return response()->json(['message' => 'No pudimos procesar tu mensaje, intenta de nuevo en un momento.'], 502);
        }

        $chat->messages()->create(['role' => 'user', 'content' => $validated['message']]);
        $chat->messages()->create(['role' => 'assistant', 'content' => $reply]);
        $chat->increment('message_count');

        return response()->json([
            'reply' => $reply,
            'limit_reached' => false,
        ]);
    }
}
