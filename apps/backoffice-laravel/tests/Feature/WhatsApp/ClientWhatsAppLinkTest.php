<?php

namespace Tests\Feature\WhatsApp;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Phone;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\TemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\CreatesRestrictedOperatorUser;
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
    use CreatesAdminUser;
    use CreatesRestrictedOperatorUser;
    use RefreshDatabase;

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
        $response->assertJson(['wa_link' => 'https://api.whatsapp.com/send?phone=528110000001', 'message' => '']);
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
            'wa_link' => 'https://api.whatsapp.com/send?phone=528110000001&text='.rawurlencode('Hola Renata Vidal, ¿cómo estás?'),
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
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'name' => 'mobile-test',
        ]);
        $client = $this->clientWithPhone();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/clients/'.$client->id.'/whatsapp-link?phone=8110000001');

        $response->assertOk();
        $response->assertJson(['wa_link' => 'https://api.whatsapp.com/send?phone=528110000001']);
    }

    private function bookingFor(Client $client, string $petName = 'Firulais', ?int $operatorId = null): SpaBooking
    {
        $pet = Pet::create(['client_id' => $client->id, 'name' => $petName]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operatorId,
            'scheduled_at' => now()->addDay()->setTime(10, 30),
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ]);
        $service = Service::create(['code' => 'WA-BANO-'.uniqid(), 'name' => 'Baño completo', 'type' => 'spa', 'price' => 250, 'suggested_price' => 250, 'duration_minutes' => 60, 'is_active' => true]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => 250]);

        return $booking;
    }

    public function test_templates_endpoint_adds_cita_context_templates_only_when_opened_from_a_booking(): void
    {
        WhatsAppTemplate::create(['name' => 'Recordatorio de cita', 'body' => 'Hola {cliente}', 'context' => 'cita', 'is_active' => true]);
        WhatsAppTemplate::create(['name' => 'Saludo directo', 'body' => 'Hola {cliente}', 'context' => 'cliente', 'is_active' => true]);
        WhatsAppTemplate::create(['name' => 'Recurrencia', 'body' => 'Hola {cliente}', 'context' => 'recurrencia', 'is_active' => true]);
        $admin = $this->admin();

        $this->actingAs($admin)->getJson(route('clients.whatsapp.templates'))
            ->assertOk()->assertJsonCount(1)->assertJsonMissing(['name' => 'Recordatorio de cita']);

        $this->actingAs($admin)->getJson(route('clients.whatsapp.templates').'?booking_id=1')
            ->assertOk()->assertJsonCount(2)->assertJsonFragment(['name' => 'Recordatorio de cita']);
    }

    public function test_link_endpoint_resolves_a_cita_template_with_the_real_booking_data(): void
    {
        $client = $this->clientWithPhone();
        $booking = $this->bookingFor($client);
        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio de cita',
            'body' => 'Hola {cliente}, te esperamos con {mascota} para {servicio} el {fecha} a las {hora}.',
            'context' => 'cita',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id.'&booking_id='.$booking->id);

        $response->assertOk();
        // Mismos formatos de fecha/hora que aplicó el request (ApplySystemSettings).
        $expected = TemplateResolver::resolve($template->body, $booking->fresh(['pet.client', 'services.service']));
        $response->assertJson(['message' => $expected]);
        $this->assertStringContainsString('Firulais para Baño completo', $expected);
        $this->assertStringNotContainsString('{', $expected);
    }

    public function test_link_endpoint_rejects_a_cita_template_without_a_booking(): void
    {
        $client = $this->clientWithPhone();
        $template = WhatsAppTemplate::create(['name' => 'Recordatorio', 'body' => 'Hola {cliente} {fecha}', 'context' => 'cita', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id)
            ->assertStatus(404);
    }

    public function test_link_endpoint_rejects_a_booking_of_another_client(): void
    {
        $client = $this->clientWithPhone();
        $otherBooking = $this->bookingFor($this->clientWithPhone('8110000002'));

        $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&booking_id='.$otherBooking->id)
            ->assertStatus(422);
    }

    public function test_link_endpoint_rejects_a_booking_the_restricted_operator_cannot_see(): void
    {
        $client = $this->clientWithPhone();
        $mine = Operator::create(['code' => 'OP-WA-1', 'name' => 'Mío', 'first_name' => 'Mío', 'is_active' => true]);
        $other = Operator::create(['code' => 'OP-WA-2', 'name' => 'Otro', 'first_name' => 'Otro', 'is_active' => true]);
        $foreign = $this->bookingFor($client, 'Ajena', $other->id);
        $user = $this->createOperatorUser(['ver agenda', 'ver clientes'], $mine);

        $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson('/api/clients/'.$client->id.'/whatsapp-link?phone=8110000001&booking_id='.$foreign->id)
            ->assertStatus(422);
    }

    public function test_booking_detail_api_exposes_the_owner_phone_for_the_whatsapp_button(): void
    {
        $client = $this->clientWithPhone();
        $booking = $this->bookingFor($client);
        $user = $this->admin();
        $token = 'test-token-'.uniqid();
        ApiToken::create(['user_id' => $user->id, 'token' => hash('sha256', $token), 'name' => 'mobile-test']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/bookings/'.$booking->id)
            ->assertOk()
            ->assertJsonPath('client.phone', '8110000001');
    }

    public function test_link_keeps_4_byte_emoji_intact_instead_of_going_through_wa_me(): void
    {
        // wa.me reemplaza estos emoji por U+FFFD (�) al redirigir en navegador de escritorio —
        // el link tiene que ir directo a api.whatsapp.com/send con el UTF-8 original.
        $client = $this->clientWithPhone();
        $template = WhatsAppTemplate::create(['name' => 'Listo', 'body' => '👋🏻 {cliente}, ya estoy listo 🐶', 'context' => 'cliente', 'is_active' => true]);

        $link = $this->actingAs($this->admin())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id)
            ->assertOk()
            ->json('wa_link');

        $this->assertStringStartsWith('https://api.whatsapp.com/send?phone=528110000001&text=', $link);
        $this->assertStringNotContainsString('wa.me', $link);
        $this->assertStringContainsString(rawurlencode('🐶'), $link);
        $this->assertStringNotContainsString('%EF%BF%BD', $link);
    }
}
