<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Client;
use App\Models\Phone;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Ícono de WhatsApp junto al teléfono del cliente (ficha de cliente y agenda) — ofrece mensaje
 * directo (sin texto) o una plantilla de contexto "cliente" preformateada con
 * TemplateResolver::resolveForClient(). Mismo controller (App\Http\Controllers\Api\ClientWhatsAppController)
 * expuesto por sesión web (routes/web.php, `clients.whatsapp.*`) y por token (routes/api.php,
 * usado por la app móvil) — este test cubre ambos.
 */
class ClientWhatsAppLinkTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function clientWithPhone(string $number = '8110000001'): Client
    {
        $client = Client::create([
            'first_name' => 'Renata',
            'apellido_paterno' => 'Vidal',
        ]);

        Phone::create(['client_id' => $client->id, 'type' => 'mobile', 'number' => $number, 'sort_order' => 0]);

        return $client;
    }

    public function test_web_templates_endpoint_returns_only_active_cliente_context_templates(): void
    {
        WhatsAppTemplate::create(['name' => 'Cita mañana', 'body' => 'Hola {cliente}', 'context' => 'cita', 'is_active' => true]);
        WhatsAppTemplate::create(['name' => 'Promo inactiva', 'body' => 'Hola {cliente}', 'context' => 'cliente', 'is_active' => false]);
        WhatsAppTemplate::create(['name' => 'Saludo directo', 'body' => 'Hola {cliente}, ¿cómo estás?', 'context' => 'cliente', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->getJson(route('clients.whatsapp.templates'));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Saludo directo']);
    }

    public function test_web_link_endpoint_returns_a_bare_wa_link_without_a_template(): void
    {
        $client = $this->clientWithPhone();

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001');

        $response->assertOk();
        $response->assertJson(['wa_link' => 'https://wa.me/528110000001', 'message' => '']);
    }

    public function test_web_link_endpoint_resolves_the_chosen_template_with_the_client_name(): void
    {
        $client = $this->clientWithPhone();
        $template = WhatsAppTemplate::create([
            'name' => 'Saludo directo',
            'body' => 'Hola {cliente}, ¿cómo estás?',
            'context' => 'cliente',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id);

        $response->assertOk();
        $response->assertJson([
            'wa_link' => 'https://wa.me/528110000001?text='.rawurlencode('Hola Renata Vidal, ¿cómo estás?'),
            'message' => 'Hola Renata Vidal, ¿cómo estás?',
        ]);
    }

    public function test_web_link_endpoint_rejects_a_phone_that_does_not_belong_to_the_client(): void
    {
        $client = $this->clientWithPhone();

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=9999999999');

        $response->assertStatus(422);
    }

    public function test_web_link_endpoint_rejects_an_inactive_or_missing_template(): void
    {
        $client = $this->clientWithPhone();
        $inactive = WhatsAppTemplate::create([
            'name' => 'Vieja',
            'body' => 'Hola {cliente}',
            'context' => 'cliente',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$inactive->id);

        $response->assertStatus(404);
    }

    public function test_a_user_without_ver_clientes_permission_cannot_reach_either_endpoint(): void
    {
        $user = User::create([
            'name' => 'Sin Permiso',
            'first_name' => 'Sin',
            'apellido_paterno' => 'Permiso',
            'email' => 'sin-permiso-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'can_login' => true,
        ]);
        $client = $this->clientWithPhone();

        $this->actingAs($user)->getJson(route('clients.whatsapp.templates'))->assertForbidden();
        $this->actingAs($user)->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001')->assertForbidden();
    }

    public function test_api_link_endpoint_works_with_a_bearer_token_for_the_mobile_app(): void
    {
        $user = $this->admin();
        $token = 'test-token-'.uniqid();
        \App\Models\ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'name' => 'mobile-test',
        ]);
        $client = $this->clientWithPhone();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/clients/'.$client->id.'/whatsapp-link?phone=8110000001');

        $response->assertOk();
        $response->assertJson(['wa_link' => 'https://wa.me/528110000001']);
    }
}
