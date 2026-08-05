<?php

namespace Tests\Feature\WhatsApp;

use App\Domain\WhatsAppMessaging\Contracts\WhatsAppSenderInterface;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    private function enableMessaging(int $hoursBefore = 24): void
    {
        app(SystemSettings::class)->saveFields('whatsapp_messaging', [
            'whatsapp_messaging_enabled' => true,
            'whatsapp_messaging_template_name' => 'recordatorio_cita',
            'whatsapp_messaging_template_language' => 'es_MX',
            'whatsapp_reminder_hours_before' => $hoursBefore,
        ]);
    }

    private function bookingWithin(int $hoursFromNow, bool $optOut = false): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz', 'receives_service_reminders' => ! $optOut]);
        $client->phones()->create(['number' => '5512345678', 'type' => 'mobile']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'BC-'.uniqid(), 'name' => 'Baño y corte', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addHours($hoursFromNow),
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 250]);

        return $booking;
    }

    public function test_sends_reminder_to_eligible_booking_within_window(): void
    {
        $this->enableMessaging(hoursBefore: 24);
        $booking = $this->bookingWithin(20);

        $this->mock(WhatsAppSenderInterface::class, function ($mock) {
            $mock->shouldReceive('sendTemplate')
                ->once()
                ->andReturn(['status' => 'sent', 'provider_message_id' => 'wamid.TEST123', 'error' => null]);
        });

        $this->artisan('whatsapp:enviar-recordatorios-cita')->assertSuccessful();

        $this->assertDatabaseHas('booking_messages', [
            'spa_booking_id' => $booking->id,
            'trigger' => 'automatic_reminder',
            'provider_message_id' => 'wamid.TEST123',
        ]);
    }

    public function test_dry_run_does_not_call_provider_nor_persist(): void
    {
        $this->enableMessaging();
        $this->bookingWithin(20);

        $this->mock(WhatsAppSenderInterface::class, function ($mock) {
            $mock->shouldReceive('sendTemplate')->never();
        });

        $this->artisan('whatsapp:enviar-recordatorios-cita --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('booking_messages', 0);
    }

    public function test_skips_booking_outside_the_configured_window(): void
    {
        $this->enableMessaging(hoursBefore: 24);
        $this->bookingWithin(48);

        $this->mock(WhatsAppSenderInterface::class, function ($mock) {
            $mock->shouldReceive('sendTemplate')->never();
        });

        $this->artisan('whatsapp:enviar-recordatorios-cita')->assertSuccessful();

        $this->assertDatabaseCount('booking_messages', 0);
    }

    public function test_does_not_duplicate_when_already_sent(): void
    {
        $this->enableMessaging();
        $booking = $this->bookingWithin(20);

        BookingMessage::create([
            'spa_booking_id' => $booking->id,
            'channel' => 'whatsapp',
            'trigger' => 'automatic_reminder',
            'phone_number' => '525512345678',
            'message_body' => 'Ya enviado antes',
            'sent_at' => now()->subMinutes(10),
        ]);

        $this->mock(WhatsAppSenderInterface::class, function ($mock) {
            $mock->shouldReceive('sendTemplate')->never();
        });

        $this->artisan('whatsapp:enviar-recordatorios-cita')->assertSuccessful();

        $this->assertDatabaseCount('booking_messages', 1);
    }

    public function test_respects_client_opt_out(): void
    {
        $this->enableMessaging();
        $this->bookingWithin(20, optOut: true);

        $this->mock(WhatsAppSenderInterface::class, function ($mock) {
            $mock->shouldReceive('sendTemplate')->never();
        });

        $this->artisan('whatsapp:enviar-recordatorios-cita')->assertSuccessful();

        $this->assertDatabaseCount('booking_messages', 0);
    }

    public function test_skips_entirely_when_messaging_disabled(): void
    {
        $this->bookingWithin(20);

        $this->mock(WhatsAppSenderInterface::class, function ($mock) {
            $mock->shouldReceive('sendTemplate')->never();
        });

        $this->artisan('whatsapp:enviar-recordatorios-cita')->assertSuccessful();

        $this->assertDatabaseCount('booking_messages', 0);
    }

    public function test_skips_when_template_not_configured(): void
    {
        app(SystemSettings::class)->saveFields('whatsapp_messaging', ['whatsapp_messaging_enabled' => true]);
        $this->bookingWithin(20);

        $this->mock(WhatsAppSenderInterface::class, function ($mock) {
            $mock->shouldReceive('sendTemplate')->never();
        });

        $this->artisan('whatsapp:enviar-recordatorios-cita')->assertSuccessful();

        $this->assertDatabaseCount('booking_messages', 0);
    }
}
