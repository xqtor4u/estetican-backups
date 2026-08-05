<?php

namespace Tests\Feature\WhatsApp;

use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class BookingMessageCalendarTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function petAndService(): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'CAL01', 'name' => 'Baño', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60]);

        return [$pet, $service];
    }

    private function booking(Pet $pet, Service $service, string $status, \DateTimeInterface|string $scheduledAt): SpaBooking
    {
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => $scheduledAt,
            'status' => $status,
            'total_estimated_price' => $service->price,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => $service->price]);

        return $booking;
    }

    private function calendarDaysFor(SpaBooking $booking, ?User $admin = null): array
    {
        $response = $this->actingAs($admin ?? $this->admin())
            ->get(route('whatsapp.bandeja', ['date' => $booking->scheduled_at->toDateString()]));

        $response->assertOk();

        return collect($response->viewData('calendarDays'))
            ->keyBy(fn ($day) => $day['date']->format('Y-m-d'))
            ->all();
    }

    public function test_completed_booking_counts_only_as_completadas(): void
    {
        [$pet, $service] = $this->petAndService();
        $booking = $this->booking($pet, $service, 'completed', now()->subDays(3)->setTime(10, 0));

        $day = $this->calendarDaysFor($booking)[$booking->scheduled_at->format('Y-m-d')];

        $this->assertSame(1, $day['counts']['completadas']);
        $this->assertSame(0, $day['counts']['recordatorio_pendiente']);
        $this->assertSame(0, $day['counts']['en_riesgo']);
    }

    public function test_scheduled_booking_without_any_message_counts_as_recordatorio_pendiente(): void
    {
        [$pet, $service] = $this->petAndService();
        $booking = $this->booking($pet, $service, 'scheduled', now()->addDays(2)->setTime(10, 0));

        $day = $this->calendarDaysFor($booking)[$booking->scheduled_at->format('Y-m-d')];

        $this->assertSame(1, $day['counts']['recordatorio_pendiente']);
    }

    public function test_scheduled_booking_with_a_message_sent_any_time_is_excluded_from_recordatorio_pendiente(): void
    {
        [$pet, $service] = $this->petAndService();
        $booking = $this->booking($pet, $service, 'scheduled', now()->addDays(2)->setTime(10, 0));

        $admin = $this->admin();

        $template = WhatsAppTemplate::create(['name' => 'Recordatorio', 'body' => 'Hola.', 'is_active' => true]);
        BookingMessage::create([
            'spa_booking_id' => $booking->id,
            'whatsapp_template_id' => $template->id,
            'phone_number' => '525512345678',
            'message_body' => 'Hola.',
            'wa_link' => 'https://wa.me/525512345678?text=Hola',
            'sent_by_user_id' => $admin->id,
            'sent_at' => now()->subDays(5),
        ]);

        $day = $this->calendarDaysFor($booking, $admin)[$booking->scheduled_at->format('Y-m-d')];

        $this->assertSame(0, $day['counts']['recordatorio_pendiente']);
    }

    public function test_past_unresolved_scheduled_booking_counts_as_en_riesgo(): void
    {
        [$pet, $service] = $this->petAndService();
        $booking = $this->booking($pet, $service, 'scheduled', now()->subDays(4)->setTime(9, 0));

        $day = $this->calendarDaysFor($booking)[$booking->scheduled_at->format('Y-m-d')];

        $this->assertSame(1, $day['counts']['en_riesgo']);
    }

    public function test_cancelled_and_no_show_bookings_count_in_no_category(): void
    {
        [$pet, $service] = $this->petAndService();
        $cancelled = $this->booking($pet, $service, 'cancelled', now()->subDays(4)->setTime(9, 0));
        $noShow = $this->booking($pet, $service, 'no_show', now()->subDays(4)->setTime(11, 0));

        $day = $this->calendarDaysFor($cancelled)[$cancelled->scheduled_at->format('Y-m-d')];

        $this->assertSame(0, $day['counts']['completadas']);
        $this->assertSame(0, $day['counts']['recordatorio_pendiente']);
        $this->assertSame(0, $day['counts']['en_riesgo']);
        $this->assertSame($noShow->scheduled_at->format('Y-m-d'), $cancelled->scheduled_at->format('Y-m-d'));
    }

    public function test_completed_booking_with_overdue_time_does_not_count_as_en_riesgo(): void
    {
        [$pet, $service] = $this->petAndService();
        $booking = $this->booking($pet, $service, 'completed', now()->subDays(10)->setTime(9, 0));

        $day = $this->calendarDaysFor($booking)[$booking->scheduled_at->format('Y-m-d')];

        $this->assertSame(1, $day['counts']['completadas']);
        $this->assertSame(0, $day['counts']['en_riesgo']);
    }

    public function test_bandeja_still_renders_with_calendar_days_present(): void
    {
        $response = $this->actingAs($this->admin())->get(route('whatsapp.bandeja'));

        $response->assertOk();
        $this->assertIsArray($response->viewData('calendarDays'));
        $this->assertNotEmpty($response->viewData('calendarDays'));
    }
}
