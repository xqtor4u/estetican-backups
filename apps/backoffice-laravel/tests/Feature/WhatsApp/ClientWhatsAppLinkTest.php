<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Phone;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Ícono de WhatsApp junto al teléfono del cliente (ficha de cliente y agenda) — ofrece mensaje
 * directo (sin texto), una plantilla de contexto "cliente" (TemplateResolver::resolveForClient(),
 * solo {cliente}) o una de contexto "general" para campañas/ofertas
 * (TemplateResolver::resolveGeneral(), {cliente}+{mascota} si aplica, el resto en blanco).
 * Mismo controller (App\Http\Controllers\Api\ClientWhatsAppController) expuesto por sesión web
 * (routes/web.php, `clients.whatsapp.*`) y por token (routes/api.php, usado por la app móvil) —
 * este test cubre ambos.
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

    public function test_web_templates_endpoint_returns_only_active_cliente_and_general_context_templates(): void
    {
        WhatsAppTemplate::create(['name' => 'Cita mañana', 'body' => 'Hola {cliente}', 'context' => 'cita', 'is_active' => true]);
        WhatsAppTemplate::create(['name' => 'Recurrencia', 'body' => 'Hola {cliente}', 'context' => 'recurrencia', 'is_active' => true]);
        WhatsAppTemplate::create(['name' => 'Promo inactiva', 'body' => 'Hola {cliente}', 'context' => 'cliente', 'is_active' => false]);
        WhatsAppTemplate::create(['name' => 'Saludo directo', 'body' => 'Hola {cliente}, ¿cómo estás?', 'context' => 'cliente', 'is_active' => true]);
        WhatsAppTemplate::create(['name' => 'Oferta de temporada', 'body' => 'Hola {cliente}, tenemos una oferta', 'context' => 'general', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->getJson(route('clients.whatsapp.templates'));

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['name' => 'Saludo directo']);
        $response->assertJsonFragment(['name' => 'Oferta de temporada']);
    }

    public function test_web_link_endpoint_resolves_a_general_template_with_client_and_single_live_pet(): void
    {
        $client = $this->clientWithPhone();
        Pet::create(['client_id' => $client->id, 'name' => 'Firulais']);
        $template = WhatsAppTemplate::create([
            'name' => 'Oferta de temporada',
            'body' => 'Hola {cliente}, tenemos una oferta especial para {mascota} este mes.',
            'context' => 'general',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Hola Renata Vidal, tenemos una oferta especial para Firulais este mes.',
        ]);
    }

    public function test_web_link_endpoint_leaves_unresolvable_general_variables_blank_not_literal(): void
    {
        $client = $this->clientWithPhone();
        $template = WhatsAppTemplate::create([
            'name' => 'Oferta con fecha',
            'body' => 'Hola {cliente}, promoción válida hasta el {fecha} en {servicio}.',
            'context' => 'general',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id);

        $response->assertOk();
        $message = $response->json('message');
        $this->assertStringNotContainsString('{fecha}', $message);
        $this->assertStringNotContainsString('{servicio}', $message);
        $this->assertSame('Hola Renata Vidal, promoción válida hasta el  en .', $message);
    }

    public function test_web_link_endpoint_leaves_mascota_blank_for_a_general_template_when_client_has_no_pet(): void
    {
        $client = $this->clientWithPhone();
        $template = WhatsAppTemplate::create([
            'name' => 'Oferta genérica',
            'body' => 'Hola {cliente}, saludos a {mascota}.',
            'context' => 'general',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id);

        $response->assertOk();
        $response->assertJson(['message' => 'Hola Renata Vidal, saludos a .']);
    }

    public function test_web_link_endpoint_leaves_mascota_blank_for_a_general_template_when_client_has_multiple_pets(): void
    {
        $client = $this->clientWithPhone();
        Pet::create(['client_id' => $client->id, 'name' => 'Firulais']);
        Pet::create(['client_id' => $client->id, 'name' => 'Michi']);
        $template = WhatsAppTemplate::create([
            'name' => 'Oferta genérica',
            'body' => 'Hola {cliente}, saludos a {mascota}.',
            'context' => 'general',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id);

        $response->assertOk();
        $response->assertJson(['message' => 'Hola Renata Vidal, saludos a .']);
    }

    public function test_web_link_endpoint_uses_the_explicit_pet_id_when_the_client_has_multiple_pets(): void
    {
        $client = $this->clientWithPhone();
        Pet::create(['client_id' => $client->id, 'name' => 'Firulais']);
        $michi = Pet::create(['client_id' => $client->id, 'name' => 'Michi']);
        $template = WhatsAppTemplate::create([
            'name' => 'Oferta genérica',
            'body' => 'Hola {cliente}, saludos a {mascota}.',
            'context' => 'general',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id.'&pet_id='.$michi->id);

        $response->assertOk();
        $response->assertJson(['message' => 'Hola Renata Vidal, saludos a Michi.']);
    }

    public function test_web_link_endpoint_rejects_a_pet_id_that_does_not_belong_to_the_client(): void
    {
        $client = $this->clientWithPhone();
        $otherClient = Client::create(['first_name' => 'Otro', 'apellido_paterno' => 'Cliente']);
        $foreignPet = Pet::create(['client_id' => $otherClient->id, 'name' => 'Ajeno']);
        $template = WhatsAppTemplate::create([
            'name' => 'Oferta genérica',
            'body' => 'Hola {cliente}, saludos a {mascota}.',
            'context' => 'general',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id.'&pet_id='.$foreignPet->id);

        $response->assertStatus(422);
    }

    public function test_web_live_pets_endpoint_returns_only_live_pets_sorted_by_name(): void
    {
        $client = $this->clientWithPhone();
        Pet::create(['client_id' => $client->id, 'name' => 'Zeus']);
        Pet::create(['client_id' => $client->id, 'name' => 'Ajax']);
        Pet::create(['client_id' => $client->id, 'name' => 'Difunto', 'death_date' => now()->subDay()]);

        $response = $this->actingAs($this->admin())->getJson(route('clients.whatsapp.live-pets', $client));

        $response->assertOk();
        $response->assertJsonCount(2);
        $names = collect($response->json())->pluck('name')->all();
        $this->assertSame(['Ajax', 'Zeus'], $names);
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
        $this->actingAs($user)->getJson(route('clients.whatsapp.live-pets', $client))->assertForbidden();
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
