<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrenceMessageFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-recurrencia-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    private function petWithExecutedService(Service $service, string $executedAt, ?string $phone = '5512345678'): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        if ($phone) {
            $client->phones()->create(['number' => $phone, 'type' => 'mobile']);
        }
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => $executedAt,
            'status' => 'completed',
            'total_estimated_price' => $service->price,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => $service->price]);

        return $pet;
    }

    public function test_recurrencias_page_renders_for_an_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('whatsapp.recurrencias'))
            ->assertOk()
            ->assertSee('Recordatorios de recurrencia');
    }

    public function test_pet_overdue_on_a_recurring_service_appears_in_the_list(): void
    {
        $service = Service::create([
            'code' => 'BAN01', 'name' => 'Baño', 'type' => 'spa',
            'price' => 250, 'duration_minutes' => 60, 'recurrence_days' => 20,
        ]);
        $pet = $this->petWithExecutedService($service, now()->subDays(25)->toDateTimeString());

        $response = $this->actingAs($this->admin())->get(route('whatsapp.recurrencias'));

        $response->assertOk();
        $response->assertSee($pet->name);
        $response->assertSee($service->name);
        $response->assertDontSee('No hay mascotas con servicio recurrente vencido');
    }

    public function test_pet_not_yet_due_does_not_appear_in_the_list(): void
    {
        $service = Service::create([
            'code' => 'BAN02', 'name' => 'Baño', 'type' => 'spa',
            'price' => 250, 'duration_minutes' => 60, 'recurrence_days' => 20,
        ]);
        $this->petWithExecutedService($service, now()->subDays(5)->toDateTimeString());

        $response = $this->actingAs($this->admin())->get(route('whatsapp.recurrencias'));

        $response->assertOk();
        $response->assertSee('No hay mascotas con servicio recurrente vencido');
    }

    public function test_service_without_recurrence_days_is_ignored(): void
    {
        $service = Service::create([
            'code' => 'EXT01', 'name' => 'Extracción de glándulas', 'type' => 'spa',
            'price' => 150, 'duration_minutes' => 20,
        ]);
        $this->petWithExecutedService($service, now()->subDays(365)->toDateTimeString());

        $response = $this->actingAs($this->admin())->get(route('whatsapp.recurrencias'));

        $response->assertOk();
        $response->assertSee('No hay mascotas con servicio recurrente vencido');
    }

    public function test_sending_a_reminder_creates_a_recurrence_message_and_returns_wa_link(): void
    {
        $service = Service::create([
            'code' => 'BAN03', 'name' => 'Baño', 'type' => 'spa',
            'price' => 250, 'duration_minutes' => 60, 'recurrence_days' => 20,
        ]);
        $pet = $this->petWithExecutedService($service, now()->subDays(25)->toDateTimeString());

        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio recurrencia',
            'body' => 'Hola {cliente}, {mascota} ya requiere su {servicio} (última vez: {ultima_fecha}, hace {dias_vencido} días de más).',
            'context' => 'recurrencia',
            'is_active' => true,
        ]);

        $key = $pet->id.':'.$service->id;

        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.recurrencias.enviar', ['key' => $key]), [
                'whatsapp_template_id' => $template->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['wa_link', 'sent_at']);
        $this->assertStringContainsString('https://wa.me/525512345678?text=', $response->json('wa_link'));

        $this->assertDatabaseHas('recurrence_messages', [
            'pet_id' => $pet->id,
            'service_id' => $service->id,
            'whatsapp_template_id' => $template->id,
            'phone_number' => '525512345678',
        ]);
    }

    public function test_preview_resolves_the_message_without_persisting_a_recurrence_message(): void
    {
        $service = Service::create([
            'code' => 'BAN05', 'name' => 'Baño', 'type' => 'spa',
            'price' => 250, 'duration_minutes' => 60, 'recurrence_days' => 20,
        ]);
        $pet = $this->petWithExecutedService($service, now()->subDays(25)->toDateTimeString());

        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio recurrencia',
            'body' => 'Hola {cliente}, {mascota} ya requiere su {servicio}.',
            'context' => 'recurrencia',
            'is_active' => true,
        ]);

        $key = $pet->id.':'.$service->id;

        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.recurrencias.preview', ['key' => $key]), [
                'whatsapp_template_id' => $template->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['message']);
        $this->assertStringContainsString('Luka', $response->json('message'));
        $this->assertStringContainsString('Baño', $response->json('message'));

        $this->assertDatabaseMissing('recurrence_messages', ['pet_id' => $pet->id, 'service_id' => $service->id]);
    }

    public function test_already_sent_today_row_is_excluded_from_select_all_but_still_checkable(): void
    {
        $service = Service::create([
            'code' => 'BAN06', 'name' => 'Baño', 'type' => 'spa',
            'price' => 250, 'duration_minutes' => 60, 'recurrence_days' => 20,
        ]);
        $pet = $this->petWithExecutedService($service, now()->subDays(25)->toDateTimeString());

        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio recurrencia', 'body' => 'Hola {cliente}.', 'context' => 'recurrencia', 'is_active' => true,
        ]);

        $admin = $this->admin();

        \App\Models\RecurrenceMessage::create([
            'pet_id' => $pet->id,
            'service_id' => $service->id,
            'whatsapp_template_id' => $template->id,
            'phone_number' => '525512345678',
            'message_body' => 'Hola.',
            'wa_link' => 'https://wa.me/525512345678?text=Hola',
            'sent_by_user_id' => $admin->id,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('whatsapp.recurrencias'));

        $response->assertOk();
        $response->assertSee('Enviado hoy');

        $key = $pet->id.':'.$service->id;
        $response->assertSee('value="'.$key.'"', false);

        preg_match('/toggleAll\(\$event\.target\.checked, \[(.*?)]\)/', $response->getContent(), $matches);
        $this->assertStringNotContainsString($key, $matches[1] ?? '');
    }

    public function test_sending_fails_gracefully_when_client_has_no_valid_phone(): void
    {
        $service = Service::create([
            'code' => 'BAN04', 'name' => 'Baño', 'type' => 'spa',
            'price' => 250, 'duration_minutes' => 60, 'recurrence_days' => 20,
        ]);
        $pet = $this->petWithExecutedService($service, now()->subDays(25)->toDateTimeString(), phone: null);

        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio recurrencia', 'body' => 'Hola {cliente}.', 'context' => 'recurrencia', 'is_active' => true,
        ]);

        $key = $pet->id.':'.$service->id;

        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.recurrencias.enviar', ['key' => $key]), [
                'whatsapp_template_id' => $template->id,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('recurrence_messages', ['pet_id' => $pet->id, 'service_id' => $service->id]);
    }
}
