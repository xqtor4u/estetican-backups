<?php

namespace Tests\Feature\GoogleCalendar;

use App\Jobs\SyncBookingToGoogleJob;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * SYNC-079 — SpaBookingObserver / SpaBookingServiceObserver encolan
 * SyncBookingToGoogleJob en cuanto cambia la agenda, y SOLO cuando el cambio
 * afecta al evento de Google.
 */
class BookingSyncObserverTest extends TestCase
{
    use RefreshDatabase;

    private function enableSync(): void
    {
        app(SystemSettings::class)->saveFields('calendario_google', [
            'google_calendar_sync_enabled' => true,
        ]);
    }

    private function makeBooking(array $overrides = []): SpaBooking
    {
        $operator = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'Operador Test']);
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return SpaBooking::create(array_merge([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addHours(2),
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ], $overrides));
    }

    /** Instancia sin `wasRecentlyCreated` — como la carga un request posterior. */
    private function reload(SpaBooking $booking): SpaBooking
    {
        return SpaBooking::findOrFail($booking->id);
    }

    public function test_creating_a_booking_queues_the_sync_job(): void
    {
        $this->enableSync();
        Bus::fake();

        $booking = $this->makeBooking();

        Bus::assertDispatched(SyncBookingToGoogleJob::class, function (SyncBookingToGoogleJob $job) use ($booking) {
            return $job->bookingId === $booking->id
                && $job->queue === 'google-calendar'
                && $job->delay !== null;
        });
    }

    public function test_changing_an_event_field_queues_the_job(): void
    {
        $this->enableSync();
        $booking = $this->reload($this->makeBooking());
        Bus::fake();

        $booking->update(['scheduled_at' => now()->addDays(3)]);

        Bus::assertDispatched(SyncBookingToGoogleJob::class, fn (SyncBookingToGoogleJob $job) => $job->bookingId === $booking->id);
    }

    public function test_changing_only_a_non_event_field_does_not_queue_the_job(): void
    {
        $this->enableSync();
        $booking = $this->reload($this->makeBooking());
        Bus::fake();

        $booking->update(['total_estimated_price' => 999]);

        Bus::assertNotDispatched(SyncBookingToGoogleJob::class);
    }

    public function test_cancelling_a_booking_queues_the_job(): void
    {
        $this->enableSync();
        $booking = $this->reload($this->makeBooking());
        Bus::fake();

        $booking->update(['status' => 'cancelled']);

        Bus::assertDispatched(SyncBookingToGoogleJob::class, fn (SyncBookingToGoogleJob $job) => $job->bookingId === $booking->id);
    }

    public function test_no_job_when_sync_is_disabled(): void
    {
        Bus::fake();

        $this->makeBooking();

        Bus::assertNotDispatched(SyncBookingToGoogleJob::class);
    }

    public function test_editing_a_service_line_queues_the_job_for_the_parent_booking(): void
    {
        $this->enableSync();
        $booking = $this->makeBooking();
        $service = Service::create(['code' => 'SVC-'.uniqid(), 'type' => 'spa', 'name' => 'Baño', 'price' => 120, 'duration_minutes' => 30]);
        Bus::fake();

        SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'current_price' => 120,
            'quantity' => 1,
        ]);

        Bus::assertDispatched(SyncBookingToGoogleJob::class, fn (SyncBookingToGoogleJob $job) => $job->bookingId === $booking->id);
    }
}
