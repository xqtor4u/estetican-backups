<?php

namespace Tests\Feature\GoogleCalendar;

use App\Domain\GoogleCalendar\Contracts\GoogleCalendarSyncServiceInterface;
use App\Jobs\SyncBookingToGoogleJob;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * SYNC-079 — el job por cita: relee el estado final, respeta el interruptor
 * maestro, no repite lo ya sincronizado y enruta a upsert/delete según el status
 * (mismo criterio que el barrido `calendario:sincronizar-google`).
 */
class SyncBookingToGoogleJobTest extends TestCase
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
        $operator->forceFill(['google_calendar_id' => 'cal-1'])->save();
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

    private function runJob(int $bookingId, GoogleCalendarSyncServiceInterface $sync): void
    {
        (new SyncBookingToGoogleJob($bookingId))->handle($sync, app(SystemSettings::class));
    }

    public function test_scheduled_booking_is_upserted(): void
    {
        $this->enableSync();
        $booking = $this->makeBooking();

        $sync = Mockery::mock(GoogleCalendarSyncServiceInterface::class);
        $sync->shouldReceive('upsertBookingEvent')->once()->with(Mockery::on(fn ($b) => $b->is($booking)));
        $sync->shouldNotReceive('deleteBookingEvent');

        $this->runJob($booking->id, $sync);
    }

    public function test_cancelled_booking_deletes_the_event(): void
    {
        $this->enableSync();
        $booking = $this->makeBooking(['status' => 'cancelled']);
        $booking->forceFill(['google_event_id' => 'evt-1'])->saveQuietly();

        $sync = Mockery::mock(GoogleCalendarSyncServiceInterface::class);
        $sync->shouldReceive('deleteBookingEvent')->once();
        $sync->shouldNotReceive('upsertBookingEvent');

        $this->runJob($booking->id, $sync);
    }

    public function test_unfulfillable_booking_is_left_untouched(): void
    {
        $this->enableSync();
        $booking = $this->makeBooking(['status' => 'unfulfillable']);

        $sync = Mockery::mock(GoogleCalendarSyncServiceInterface::class);
        $sync->shouldNotReceive('upsertBookingEvent');
        $sync->shouldNotReceive('deleteBookingEvent');

        $this->runJob($booking->id, $sync);
    }

    public function test_already_synced_booking_is_skipped(): void
    {
        $this->enableSync();
        $booking = $this->makeBooking();
        // google_synced_at >= updated_at ⇒ nada pendiente.
        DB::table('spa_bookings')->where('id', $booking->id)->update(['google_synced_at' => $booking->updated_at]);

        $sync = Mockery::mock(GoogleCalendarSyncServiceInterface::class);
        $sync->shouldNotReceive('upsertBookingEvent');
        $sync->shouldNotReceive('deleteBookingEvent');

        $this->runJob($booking->id, $sync);
    }

    public function test_does_nothing_when_sync_is_disabled(): void
    {
        $booking = $this->makeBooking();

        $sync = Mockery::mock(GoogleCalendarSyncServiceInterface::class);
        $sync->shouldNotReceive('upsertBookingEvent');
        $sync->shouldNotReceive('deleteBookingEvent');

        $this->runJob($booking->id, $sync);
    }

    public function test_missing_booking_is_a_no_op(): void
    {
        $this->enableSync();

        $sync = Mockery::mock(GoogleCalendarSyncServiceInterface::class);
        $sync->shouldNotReceive('upsertBookingEvent');
        $sync->shouldNotReceive('deleteBookingEvent');

        $this->runJob(999999, $sync);
    }
}
