<?php

namespace Tests\Feature\GoogleCalendar;

use App\Domain\GoogleCalendar\Services\GoogleCalendarSyncService;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use Google\Service\Calendar as GoogleCalendarApi;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Crear / mover / borrar el evento de una cita en el calendario del operador NO debe
 * disparar correos de Google: cada llamada a `events->{insert,update,delete}` lleva
 * `['sendUpdates' => 'none']`. La sincronización es interna (EstetiCAN → Google); el
 * operador ya se entera por la app.
 */
class EventSyncNoNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function service(GoogleCalendarApi $fake): GoogleCalendarSyncService
    {
        return new class($fake) extends GoogleCalendarSyncService
        {
            public function __construct(private readonly GoogleCalendarApi $fake)
            {
                parent::__construct(app(SystemSettings::class));
            }

            protected function client(): ?GoogleCalendarApi
            {
                return $this->fake;
            }
        };
    }

    private function booking(?string $eventId = null): SpaBooking
    {
        $operator = Operator::create([
            'code' => 'OP-'.uniqid(),
            'name' => 'Operador Test',
        ]);
        $operator->forceFill(['google_calendar_id' => 'cal-1'])->save();
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addHours(2),
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ]);

        if ($eventId) {
            $booking->forceFill(['google_event_id' => $eventId])->saveQuietly();
        }

        return $booking->fresh();
    }

    public function test_insert_passes_send_updates_none(): void
    {
        $events = Mockery::mock();
        $created = Mockery::mock();
        $created->shouldReceive('getId')->andReturn('evt-new');
        $events->shouldReceive('insert')
            ->once()
            ->with('cal-1', Mockery::type(GoogleEvent::class), ['sendUpdates' => 'none'])
            ->andReturn($created);

        $fake = Mockery::mock(GoogleCalendarApi::class);
        $fake->events = $events;

        $this->service($fake)->upsertBookingEvent($this->booking());
    }

    public function test_update_passes_send_updates_none(): void
    {
        $events = Mockery::mock();
        $events->shouldReceive('update')
            ->once()
            ->with('cal-1', 'evt-1', Mockery::type(GoogleEvent::class), ['sendUpdates' => 'none'])
            ->andReturn(Mockery::mock());

        $fake = Mockery::mock(GoogleCalendarApi::class);
        $fake->events = $events;

        $this->service($fake)->upsertBookingEvent($this->booking('evt-1'));
    }

    public function test_delete_passes_send_updates_none(): void
    {
        $events = Mockery::mock();
        $events->shouldReceive('delete')
            ->once()
            ->with('cal-1', 'evt-1', ['sendUpdates' => 'none'])
            ->andReturn(Mockery::mock());

        $fake = Mockery::mock(GoogleCalendarApi::class);
        $fake->events = $events;

        $this->service($fake)->deleteBookingEvent($this->booking('evt-1'));
    }
}
