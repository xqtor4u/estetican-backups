<?php

namespace Tests\Feature;

use App\Mail\ServiceSummaryMail;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ClientCommunicationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-comm-prefs-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    public function test_client_defaults_to_receiving_everything(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz', 'email' => 'ana@example.com']);
        $client->refresh(); // los defaults de columna del schema no quedan en memoria tras create()

        $this->assertTrue($client->receives_offers);
        $this->assertTrue($client->receives_service_reminders);
        $this->assertTrue($client->receives_job_updates);
        $this->assertTrue($client->receives_account_statements);
        $this->assertTrue($client->receives_other_notifications);
    }

    public function test_staff_can_update_client_communication_preferences_from_edit_form(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz', 'email' => 'ana@example.com']);
        $phone = $client->phones()->create(['number' => '5512345678', 'type' => 'mobile']);

        $response = $this->actingAs($this->admin())->put(route('clients.update', $client), [
            'first_name' => 'Ana',
            'apellido_paterno' => 'Ruiz',
            'email' => 'ana@example.com',
            'phones' => [
                ['id' => $phone->id, 'number' => '5512345678', 'type' => 'mobile'],
            ],
            'receives_offers' => '0',
            'receives_service_reminders' => '1',
            'receives_job_updates' => '1',
            'receives_account_statements' => '1',
            'receives_other_notifications' => '1',
        ]);

        $response->assertRedirect();

        $client->refresh();
        $this->assertFalse($client->receives_offers);
        $this->assertTrue($client->receives_service_reminders);
    }

    public function test_sending_a_reminder_is_blocked_when_client_opted_out_of_service_reminders(): void
    {
        Mail::fake();

        $client = Client::create([
            'first_name' => 'Ana', 'apellido_paterno' => 'Ruiz', 'email' => 'ana@example.com',
            'receives_service_reminders' => false,
        ]);
        $client->phones()->create(['number' => '5512345678', 'type' => 'mobile']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);
        $template = WhatsAppTemplate::create(['name' => 'Recordatorio', 'body' => 'Hola {cliente}.', 'is_active' => true]);
        $admin = $this->admin();

        $waResponse = $this->actingAs($admin)
            ->postJson(route('whatsapp.bandeja.enviar', $booking), ['whatsapp_template_id' => $template->id]);
        $waResponse->assertStatus(422);

        $emailResponse = $this->actingAs($admin)
            ->postJson(route('whatsapp.bandeja.enviar', $booking), [
                'whatsapp_template_id' => $template->id,
                'channel' => 'email',
            ]);
        $emailResponse->assertStatus(422);

        $this->assertDatabaseMissing('booking_messages', ['spa_booking_id' => $booking->id]);
        Mail::assertNothingSent();
    }

    public function test_recurrence_reminder_is_blocked_when_client_opted_out(): void
    {
        Mail::fake();

        $client = Client::create([
            'first_name' => 'Ana', 'apellido_paterno' => 'Ruiz', 'email' => 'ana@example.com',
            'receives_service_reminders' => false,
        ]);
        $client->phones()->create(['number' => '5512345678', 'type' => 'mobile']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create([
            'code' => 'PREF01', 'name' => 'Baño', 'type' => 'spa',
            'price' => 250, 'duration_minutes' => 60, 'recurrence_days' => 20,
        ]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->subDays(25),
            'status' => 'completed',
            'total_estimated_price' => $service->price,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => $service->price]);

        $template = WhatsAppTemplate::create([
            'name' => 'Recordatorio recurrencia', 'body' => 'Hola {cliente}.', 'context' => 'recurrencia', 'is_active' => true,
        ]);

        $key = $pet->id.':'.$service->id;

        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.recurrencias.enviar', ['key' => $key]), [
                'whatsapp_template_id' => $template->id,
                'channel' => 'email',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('recurrence_messages', ['pet_id' => $pet->id, 'service_id' => $service->id]);
        Mail::assertNothingSent();
    }

    public function test_service_summary_mail_is_not_sent_when_client_opted_out_of_job_updates(): void
    {
        Mail::fake();

        SystemSetting::create(['section' => 'clinical', 'key' => 'operational_auto_email_report', 'type' => 'boolean', 'value' => '1']);

        $client = Client::create([
            'first_name' => 'Ana', 'apellido_paterno' => 'Ruiz', 'email' => 'ana@example.com',
            'receives_job_updates' => false,
        ]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'full_name' => 'Jose', 'is_active' => true]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => 0,
        ]);

        $this->actingAs($this->admin())->put(route('agenda.update', $booking), ['status' => 'completed']);

        Mail::assertNotSent(ServiceSummaryMail::class);
    }

    public function test_public_preferences_page_shows_and_updates_with_valid_signed_url(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz', 'email' => 'ana@example.com']);

        $showUrl = URL::temporarySignedRoute('client-preferences.show', now()->addYear(), ['client' => $client->id]);

        $showResponse = $this->get($showUrl);
        $showResponse->assertOk();
        $showResponse->assertSee('Ana Ruiz');

        $updateUrl = URL::temporarySignedRoute('client-preferences.update', now()->addYear(), ['client' => $client->id]);

        $updateResponse = $this->post($updateUrl, [
            'receives_offers' => '0',
            'receives_service_reminders' => '0',
        ]);
        $updateResponse->assertRedirect();

        $client->refresh();
        $this->assertFalse($client->receives_offers);
        $this->assertFalse($client->receives_service_reminders);
        $this->assertFalse($client->receives_job_updates);
    }

    public function test_public_preferences_page_rejects_a_tampered_url(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        $response = $this->get(route('client-preferences.show', ['client' => $client->id]));

        $response->assertForbidden();
    }
}
