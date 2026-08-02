<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Pet;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `SpaBooking::alertReason()` es la fuente única para la agenda web (AgUniInd) y
 * espeja el criterio del móvil (`agendaAlertKind()`) y de la API (`AgendaController::vencidas()`).
 */
class SpaBookingAlertReasonTest extends TestCase
{
    use RefreshDatabase;

    private function booking(string $status, \Carbon\Carbon $scheduledAt, ?int $durationMinutes = 30): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Mascota-'.uniqid()]);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => $scheduledAt,
            'status' => $status,
            'duration_minutes' => $durationMinutes,
            'total_estimated_price' => 100,
        ]);
    }

    public function test_scheduled_booking_hours_late_is_not_started(): void
    {
        $booking = $this->booking('scheduled', now()->subHours(2));

        $this->assertSame('not_started', $booking->alertReason());
    }

    public function test_scheduled_booking_within_grace_period_has_no_alert(): void
    {
        $booking = $this->booking('scheduled', now()->subMinutes(5));

        $this->assertNull($booking->alertReason(graceMinutes: 15));
    }

    public function test_work_order_past_its_expected_duration_is_overdue(): void
    {
        $booking = $this->booking('work_order', now()->subHours(3), durationMinutes: 30);

        $this->assertSame('overdue', $booking->alertReason());
    }

    public function test_work_order_still_within_duration_has_no_alert(): void
    {
        $booking = $this->booking('work_order', now()->subMinutes(10), durationMinutes: 30);

        $this->assertNull($booking->alertReason());
    }

    public function test_work_order_scheduled_in_the_future_is_flagged(): void
    {
        $booking = $this->booking('work_order', now()->addHours(2), durationMinutes: 30);

        $this->assertSame('future', $booking->alertReason());
    }

    public function test_completed_cancelled_and_no_show_never_have_an_alert(): void
    {
        $completed = $this->booking('completed', now()->subDays(2));
        $cancelled = $this->booking('cancelled', now()->subDays(2));
        $noShow = $this->booking('no_show', now()->subDays(2));

        $this->assertNull($completed->alertReason());
        $this->assertNull($cancelled->alertReason());
        $this->assertNull($noShow->alertReason());
    }
}
