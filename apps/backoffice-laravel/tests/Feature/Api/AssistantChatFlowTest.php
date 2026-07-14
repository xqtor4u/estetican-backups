<?php

namespace Tests\Feature\Api;

use App\Models\ServiceAiChat;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantChatFlowTest extends TestCase
{
    use RefreshDatabase;

    private function enableAssistant(array $overrides = []): void
    {
        app(SystemSettings::class)->save('ai_assistant', array_merge([
            'ai_assistant_enabled' => true,
            'ai_assistant_api_key' => 'sk-ant-test-key',
            'ai_assistant_model' => 'claude-haiku-4-5-20251001',
            'ai_assistant_extra_prompt' => '',
            'ai_assistant_cta_label' => 'Agenda tu cita',
            'ai_assistant_cta_url' => 'https://wa.me/5215500000000',
            'ai_assistant_site_token' => 'token-secreto',
            'ai_assistant_allowed_origin' => 'https://www.estetican.org',
        ], $overrides));
    }

    private function fakeAnthropicReply(string $text = 'Contamos con baño, corte y spa para tu mascota.'): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_test',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => $text],
                ],
                'model' => 'claude-haiku-4-5-20251001',
                'stop_reason' => 'end_turn',
            ], 200),
        ]);
    }

    public function test_chat_rejects_request_without_site_token(): void
    {
        $this->enableAssistant();

        $response = $this->postJson('/api/assistant/chat', [
            'session_uuid' => 'sin-token',
            'message' => 'Hola',
        ]);

        $response->assertStatus(403);
    }

    public function test_chat_returns_404_when_assistant_disabled(): void
    {
        $this->enableAssistant(['ai_assistant_enabled' => false]);

        $response = $this->withHeaders(['X-Widget-Token' => 'token-secreto'])
            ->postJson('/api/assistant/chat', [
                'session_uuid' => 'deshabilitado',
                'message' => 'Hola',
            ]);

        $response->assertStatus(404);
    }

    public function test_chat_returns_ai_reply_and_persists_conversation(): void
    {
        $this->enableAssistant();
        $this->fakeAnthropicReply();

        $response = $this->withHeaders(['X-Widget-Token' => 'token-secreto'])
            ->postJson('/api/assistant/chat', [
                'session_uuid' => 'sesion-1',
                'message' => '¿Qué servicios tienen?',
            ]);

        $response->assertOk();
        $response->assertJson(['limit_reached' => false]);
        $this->assertStringContainsString('baño', $response->json('reply'));

        $chat = ServiceAiChat::where('session_uuid', 'sesion-1')->first();
        $this->assertNotNull($chat);
        $this->assertSame(1, $chat->message_count);
        $this->assertCount(2, $chat->messages);
    }

    public function test_chat_enforces_message_cap_without_calling_anthropic(): void
    {
        $this->enableAssistant();
        Http::fake();

        ServiceAiChat::create(['session_uuid' => 'sesion-agotada', 'message_count' => 30]);

        $response = $this->withHeaders(['X-Widget-Token' => 'token-secreto'])
            ->postJson('/api/assistant/chat', [
                'session_uuid' => 'sesion-agotada',
                'message' => 'Otra pregunta',
            ]);

        $response->assertOk();
        $response->assertJson(['limit_reached' => true]);
        Http::assertNothingSent();
    }

    public function test_cors_preflight_is_answered_for_allowed_origin(): void
    {
        $this->enableAssistant();

        $response = $this->call('OPTIONS', '/api/assistant/chat', [], [], [], [
            'HTTP_ORIGIN' => 'https://www.estetican.org',
        ]);

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://www.estetican.org');
    }

    public function test_config_endpoint_exposes_only_non_sensitive_fields(): void
    {
        $this->enableAssistant();

        $response = $this->withHeaders(['X-Widget-Token' => 'token-secreto'])
            ->getJson('/api/assistant/config');

        $response->assertOk();
        $response->assertJson([
            'enabled' => true,
            'cta_label' => 'Agenda tu cita',
            'cta_url' => 'https://wa.me/5215500000000',
        ]);
        $response->assertJsonMissing(['ai_assistant_api_key']);
    }
}
