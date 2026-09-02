<?php

namespace Tests\Feature\GoogleCalendar;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SYNC-079 — cadencia del sync a Google Calendar:
 *  - `calendario:sincronizar-google` (aprovisionamiento + reconciliación) pasó de
 *    cada 5 min a cada hora.
 *  - se agrega un drenado de la cola `google-calendar` cada minuto, que solo
 *    arranca un proceso si la cola tiene algo (guarda `->when(...)`).
 */
class GoogleCalendarScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** @return Collection<int, Event> */
    private function events()
    {
        return collect(app(Schedule::class)->events());
    }

    public function test_reconciliation_sweep_runs_hourly(): void
    {
        $event = $this->events()->first(
            fn (Event $e) => str_contains((string) $e->command, 'calendario:sincronizar-google')
        );

        $this->assertNotNull($event, 'No se encontró el evento programado calendario:sincronizar-google.');
        $this->assertSame('0 * * * *', $event->expression);
    }

    public function test_google_calendar_queue_is_drained_every_minute_only_when_it_has_jobs(): void
    {
        $event = $this->events()->first(
            fn (Event $e) => str_contains((string) $e->command, 'queue:work')
                && str_contains((string) $e->command, "--queue='google-calendar'")
        );

        $this->assertNotNull($event, 'No se encontró el drenado programado de la cola google-calendar.');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertStringContainsString('--stop-when-empty', (string) $event->command);
    }

    public function test_drain_guard_skips_when_the_queue_is_empty_and_passes_when_it_has_a_job(): void
    {
        $event = $this->events()->first(
            fn (Event $e) => str_contains((string) $e->command, 'queue:work')
                && str_contains((string) $e->command, "--queue='google-calendar'")
        );
        $this->assertNotNull($event);

        // Cola vacía → la guarda ->when() corta, no se arranca ningún proceso.
        $this->assertFalse($event->filtersPass($this->app));

        DB::table('jobs')->insert([
            'queue' => 'google-calendar',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->assertTrue($event->filtersPass($this->app));
    }
}
