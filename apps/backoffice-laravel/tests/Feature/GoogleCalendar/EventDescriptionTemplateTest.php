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
 * Plantilla configurable (SystemSettings → calendario_google.google_calendar_description_template)
 * para la descripción del evento de Google Calendar — variables resueltas por
 * TemplateResolver::resolveForCalendarEvent().
 */
class EventDescriptionTemplateTest extends TestCase
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

    private function booking(array $overrides = []): SpaBooking
    {
        $operator = Operator::create(['code' => 'OP-'.uniqid(), 'name' => 'Operador Test', 'first_name' => 'Operador', 'apellido_paterno' => 'Test']);
        $operator->forceFill(['google_calendar_id' => 'cal-1'])->save();
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return SpaBooking::create(array_merge([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addHours(2),
            'status' => 'scheduled',
            'total_estimated_price' => 250,
        ], $overrides))->fresh();
    }

    private function insertedEvent(SpaBooking $booking): GoogleEvent
    {
        $captured = null;

        $events = Mockery::mock();
        $created = Mockery::mock();
        $created->shouldReceive('getId')->andReturn('evt-new');
        $events->shouldReceive('insert')
            ->once()
            ->with('cal-1', Mockery::on(function (GoogleEvent $event) use (&$captured) {
                $captured = $event;

                return true;
            }), ['sendUpdates' => 'none'])
            ->andReturn($created);

        $fake = Mockery::mock(GoogleCalendarApi::class);
        $fake->events = $events;

        $this->service($fake)->upsertBookingEvent($booking);

        return $captured;
    }

    public function test_default_template_includes_operator_client_and_phone(): void
    {
        $booking = $this->booking(['order_folio' => 'F-100', 'notes' => 'Trae collar isabelino']);

        $event = $this->insertedEvent($booking);

        $description = $event->getDescription();
        $this->assertStringContainsString('Cliente: Ana Ruiz', $description);
        $this->assertStringContainsString('Operador: Operador Test', $description);
        $this->assertStringContainsString('Folio: F-100', $description);
        $this->assertStringContainsString('Notas: Trae collar isabelino', $description);
    }

    public function test_blank_folio_and_notes_leave_the_placeholder_empty_without_error(): void
    {
        $booking = $this->booking();

        $event = $this->insertedEvent($booking);

        $this->assertStringContainsString("Folio: \n", $event->getDescription());
        $this->assertStringEndsWith('Notas: ', $event->getDescription());
    }

    public function test_custom_template_from_settings_is_used(): void
    {
        app(SystemSettings::class)->saveFields('calendario_google', [
            'google_calendar_description_template' => 'Mascota {mascota} con {operador} el {fecha} a las {hora}',
        ]);

        $booking = $this->booking();

        $event = $this->insertedEvent($booking);

        $this->assertSame(
            'Mascota Luka con Operador Test el '.$booking->scheduled_at->format('d/m/Y').' a las '.$booking->scheduled_at->format('h:i A'),
            $event->getDescription(),
        );
    }
}
