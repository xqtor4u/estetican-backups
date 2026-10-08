<?php

namespace Tests\Feature\Planning;

use App\Domain\Planning\Series\BookingSeriesService;
use App\Domain\Planning\Series\RecurrenceRule;
use App\Models\Client;
use App\Models\NonWorkingDay;
use App\Models\Operator;
use App\Models\OperatorUnavailability;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * ZEUS-047: armado de una serie recurrente — cada fecha se valida como una cita normal y, si
 * no sirve (festivo, operador ocupado, mascota encimada), se recorre sola.
 */
class BookingSeriesServiceTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private Pet $pet;

    private Operator $operator;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-07 08:00:00')); // miércoles

        $this->actingAs($this->createAdminUser());
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $this->pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $this->operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $this->service = Service::create(['code' => 'BC01', 'name' => 'Baño y corte', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60, 'open_to_all_operators' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function template(): array
    {
        return [
            'pet_id' => $this->pet->id,
            'operator_id' => $this->operator->id,
            'lines' => [[
                'service_id' => $this->service->id,
                'service_name' => $this->service->name,
                'operator_id' => $this->operator->id,
                'duration_minutes' => 60,
                'price' => 250,
            ]],
        ];
    }

    private function weekly(string $until = '2026-11-04'): SpaBookingSeries
    {
        return app(BookingSeriesService::class)->create(
            $this->template(),
            RecurrenceRule::everyNDays(7),
            Carbon::parse('2026-10-14 11:00'),
            Carbon::parse($until),
        );
    }

    public function test_creates_every_booking_as_pre_scheduled_linked_to_the_series(): void
    {
        $series = $this->weekly();

        $this->assertSame(SpaBookingSeries::STATUS_PENDING_REVIEW, $series->status);
        $this->assertSame(
            ['2026-10-14 11:00', '2026-10-21 11:00', '2026-10-28 11:00', '2026-11-04 11:00'],
            $series->bookings->map(fn ($b) => $b->scheduled_at->format('Y-m-d H:i'))->all()
        );
        $this->assertSame(4, $series->remainingCount());

        $booking = $series->bookings->first();
        $this->assertSame('scheduled', $booking->status);
        $this->assertSame(60, (int) $booking->duration_minutes);
        $this->assertNull($booking->series_original_at);
        $this->assertDatabaseHas('spa_booking_services', [
            'spa_booking_id' => $booking->id,
            'service_id' => $this->service->id,
            'operator_id' => $this->operator->id,
            'duration_minutes' => 60,
        ]);
    }

    public function test_a_holiday_moves_the_booking_to_the_next_day_at_the_same_time(): void
    {
        NonWorkingDay::create(['date' => '2026-10-21', 'reason' => 'Festivo local']);

        $series = $this->weekly();

        $moved = $series->bookings->firstWhere('series_original_at', '!=', null);
        $this->assertSame('2026-10-22 11:00', $moved->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-21 11:00', $moved->series_original_at->format('Y-m-d H:i'));
        $this->assertStringContainsString('Festivo local', $moved->series_move_reason);
        $this->assertCount(4, $series->bookings);
    }

    public function test_a_busy_operator_moves_the_booking_later_the_same_day(): void
    {
        SpaBooking::create(['pet_id' => Pet::create(['client_id' => $this->pet->client_id, 'name' => 'Otro'])->id, 'operator_id' => $this->operator->id, 'scheduled_at' => '2026-10-28 11:00:00', 'duration_minutes' => 60, 'status' => 'scheduled', 'total_estimated_price' => 0]);

        $series = $this->weekly();

        $moved = $series->bookings->firstWhere('series_original_at', '!=', null);
        $this->assertSame('2026-10-28 12:00', $moved->scheduled_at->format('Y-m-d H:i'));
        $this->assertStringContainsString('ya tiene una cita', $moved->series_move_reason);
    }

    public function test_the_pet_cannot_overlap_itself(): void
    {
        // Misma mascota, otro operador, a la misma hora: el operador está libre pero la mascota no.
        $other = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Rosa', 'first_name' => 'Rosa', 'is_active' => true]);
        SpaBooking::create(['pet_id' => $this->pet->id, 'operator_id' => $other->id, 'scheduled_at' => '2026-10-14 11:30:00', 'duration_minutes' => 60, 'status' => 'scheduled', 'total_estimated_price' => 0]);

        $series = $this->weekly();

        $first = $series->bookings->first();
        $this->assertSame('2026-10-14 12:30', $first->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('La mascota ya tiene otra cita en ese horario.', $first->series_move_reason);
    }

    public function test_a_date_with_no_room_in_the_search_window_is_skipped_and_recorded(): void
    {
        OperatorUnavailability::create(['operator_id' => $this->operator->id, 'starts_at' => '2026-10-20 00:00:00', 'ends_at' => '2026-11-10 23:59:00', 'reason' => 'Vacaciones']);

        $series = $this->weekly('2026-10-21');

        $this->assertCount(1, $series->bookings);
        $this->assertSame('2026-10-21 11:00:00', $series->skipped_occurrences[0]['original_at'] ?? null);
        $this->assertStringContainsString('vacaciones', $series->skipped_occurrences[0]['reason']);
    }

    public function test_plan_does_not_write_anything(): void
    {
        app(BookingSeriesService::class)->plan($this->template(), RecurrenceRule::everyNDays(7), Carbon::parse('2026-10-14 11:00'), Carbon::parse('2026-11-04'));

        $this->assertDatabaseCount('spa_bookings', 0);
        $this->assertDatabaseCount('spa_booking_series', 0);
    }
}
