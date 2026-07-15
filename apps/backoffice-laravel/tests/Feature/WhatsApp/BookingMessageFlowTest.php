<?php

namespace Tests\Feature\WhatsApp;

use App\Mail\TemplateMessageMail;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingMessageFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-whatsapp-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    public function test_bandeja_and_plantillas_pages_render_for_an_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('whatsapp.bandeja'))
            ->assertOk()
            ->assertSee('Bandeja diaria de recordatorios');

        $this->actingAs($admin)
            ->get(route('whatsapp.plantillas.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('whatsapp.plantillas.create'))
            ->assertOk();
    }

    public function test_sending_a_reminder_creates_a_booking_message_and_returns_wa_link(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $client->phones()->create(['number' => '5512345678', 'type' => 'mobile']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'BC01', 'name' => 'Baño y corte', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 250]);

        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio',
            'body' => 'Hola {cliente}, recordamos la cita de {mascota}.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.bandeja.enviar', $booking), [
                'whatsapp_template_id' => $template->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['wa_link', 'sent_at']);
        $this->assertStringContainsString('https://wa.me/525512345678?text=', $response->json('wa_link'));

        $this->assertDatabaseHas('booking_messages', [
            'spa_booking_id' => $booking->id,
            'whatsapp_template_id' => $template->id,
            'phone_number' => '525512345678',
        ]);
    }

    public function test_preview_resolves_the_message_without_persisting_a_booking_message(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $client->phones()->create(['number' => '5512345678', 'type' => 'mobile']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'BC02', 'name' => 'Baño y corte', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 250]);

        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio',
            'body' => 'Hola {cliente}, recordamos la cita de {mascota}.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.bandeja.preview', $booking), [
                'whatsapp_template_id' => $template->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $this->assertStringContainsString('Luka', $response->json('message'));

        $this->assertDatabaseMissing('booking_messages', ['spa_booking_id' => $booking->id]);
    }

    public function test_already_sent_today_row_is_flagged_in_the_row_config_for_the_frontend(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $client->phones()->create(['number' => '5512345678', 'type' => 'mobile']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'BC03', 'name' => 'Baño y corte', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 250]);

        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio', 'body' => 'Hola {cliente}.', 'is_active' => true,
        ]);

        $admin = $this->admin();

        BookingMessage::create([
            'spa_booking_id' => $booking->id,
            'whatsapp_template_id' => $template->id,
            'phone_number' => '525512345678',
            'message_body' => 'Hola.',
            'wa_link' => 'https://wa.me/525512345678?text=Hola',
            'sent_by_user_id' => $admin->id,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('whatsapp.bandeja', ['date' => $booking->scheduled_at->toDateString()]));

        $response->assertOk();
        $response->assertSee('Enviado hoy');

        // La exclusión de "seleccionar todos" ahora la calcula el frontend (Alpine, `eligibleIds`)
        // a partir del flag `alreadySentToday` de cada fila — se verifica que el flag llegue
        // correcto en la config embebida, en vez de una lista de IDs armada en el servidor.
        preg_match(
            '/\\\\u0022id\\\\u0022:'.$booking->id.',.*?\\\\u0022alreadySentToday\\\\u0022:(true|false)/',
            $response->getContent(),
            $matches
        );
        $this->assertSame('true', $matches[1] ?? null);
    }

    public function test_sending_fails_gracefully_when_client_has_no_valid_phone(): void
    {
        $client = Client::create(['first_name' => 'Beto', 'apellido_paterno' => 'Soto']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Rocko']);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);
        $template = WhatsAppTemplate::create(['name' => 'Recordatorio', 'body' => 'Hola {cliente}.', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.bandeja.enviar', $booking), [
                'whatsapp_template_id' => $template->id,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('booking_messages', ['spa_booking_id' => $booking->id]);
    }

    public function test_sending_by_email_creates_a_booking_message_and_sends_mail(): void
    {
        Mail::fake();

        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz', 'email' => 'ana@example.com']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ]);

        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio',
            'subject' => 'Recordatorio para {mascota}',
            'body' => 'Hola {cliente}, recordamos la cita de {mascota}.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.bandeja.enviar', $booking), [
                'whatsapp_template_id' => $template->id,
                'channel' => 'email',
            ]);

        $response->assertOk();
        $response->assertJsonPath('channel', 'email');

        $this->assertDatabaseHas('booking_messages', [
            'spa_booking_id' => $booking->id,
            'whatsapp_template_id' => $template->id,
            'channel' => 'email',
            'email_address' => 'ana@example.com',
        ]);

        Mail::assertSent(TemplateMessageMail::class, function (TemplateMessageMail $mail) {
            return $mail->emailSubject === 'Recordatorio para Luka'
                && str_contains($mail->messageBody, 'Luka')
                && $mail->hasTo('ana@example.com');
        });
    }

    public function test_sending_by_email_fails_gracefully_when_client_has_no_email(): void
    {
        Mail::fake();

        $client = Client::create(['first_name' => 'Beto', 'apellido_paterno' => 'Soto']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Rocko']);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);
        $template = WhatsAppTemplate::create(['name' => 'Recordatorio', 'body' => 'Hola {cliente}.', 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.bandeja.enviar', $booking), [
                'whatsapp_template_id' => $template->id,
                'channel' => 'email',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('booking_messages', ['spa_booking_id' => $booking->id]);
        Mail::assertNothingSent();
    }
}
