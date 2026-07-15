<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-agenda-status-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    private function bookingWithStatus(string $status): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Mascota-'.$status]);
        $service = Service::create(['code' => 'ST'.uniqid(), 'name' => 'Servicio', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => $status,
            'total_estimated_price' => 100,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 100]);

        return $booking;
    }

    private function seedAllStatuses(): array
    {
        return [
            'scheduled' => $this->bookingWithStatus('scheduled'),
            'work_order' => $this->bookingWithStatus('work_order'),
            'completed' => $this->bookingWithStatus('completed'),
            'cancelled' => $this->bookingWithStatus('cancelled'),
            'no_show' => $this->bookingWithStatus('no_show'),
            'unfulfillable' => $this->bookingWithStatus('unfulfillable'),
        ];
    }

    public function test_no_query_params_defaults_to_scheduled_and_work_order_only(): void
    {
        $bookings = $this->seedAllStatuses();

        $response = $this->actingAs($this->admin())->get(route('agenda.index'));

        $response->assertOk();
        $response->assertSee($bookings['scheduled']->pet->name);
        $response->assertSee($bookings['work_order']->pet->name);
        $response->assertDontSee($bookings['completed']->pet->name);
        $response->assertDontSee($bookings['cancelled']->pet->name);
    }

    public function test_status_touched_with_no_boxes_checked_shows_everything(): void
    {
        $bookings = $this->seedAllStatuses();

        $response = $this->actingAs($this->admin())->get(route('agenda.index', ['status_touched' => 1]));

        $response->assertOk();
        foreach ($bookings as $booking) {
            $response->assertSee($booking->pet->name);
        }
    }

    public function test_status_touched_with_completed_checked_shows_only_completed(): void
    {
        $bookings = $this->seedAllStatuses();

        $response = $this->actingAs($this->admin())->get(route('agenda.index', [
            'status_touched' => 1,
            'status' => ['completed'],
        ]));

        $response->assertOk();
        $response->assertSee($bookings['completed']->pet->name);
        // "scheduled"/"work_order" always show up in the always-on daily timeline widget
        // regardless of the Estado filter (a separate, fixed concept from SpaBookingController::index()),
        // so only assert against a status that ISN'T part of that fixed timeline.
        $response->assertDontSee($bookings['cancelled']->pet->name);
        $response->assertDontSee($bookings['no_show']->pet->name);
    }

    public function test_status_touched_with_scheduled_and_work_order_matches_implicit_default(): void
    {
        $bookings = $this->seedAllStatuses();

        $response = $this->actingAs($this->admin())->get(route('agenda.index', [
            'status_touched' => 1,
            'status' => ['scheduled', 'work_order'],
        ]));

        $response->assertOk();
        $response->assertSee($bookings['scheduled']->pet->name);
        $response->assertSee($bookings['work_order']->pet->name);
        $response->assertDontSee($bookings['completed']->pet->name);
    }

    public function test_invalid_status_value_is_silently_dropped(): void
    {
        $bookings = $this->seedAllStatuses();

        $response = $this->actingAs($this->admin())->get(route('agenda.index', [
            'status_touched' => 1,
            'status' => ['completed', 'bogus'],
        ]));

        $response->assertOk();
        $response->assertSee($bookings['completed']->pet->name);
        $response->assertDontSee($bookings['cancelled']->pet->name);
    }

    public function test_month_calendar_view_also_respects_the_status_filter(): void
    {
        $bookings = $this->seedAllStatuses();

        $response = $this->actingAs($this->admin())->get(route('agenda.index', [
            'cal_view' => 'month',
            'status_touched' => 1,
            'status' => ['completed'],
        ]));

        $response->assertOk();
        $response->assertSee($bookings['completed']->pet->name);
        $response->assertDontSee($bookings['scheduled']->pet->name);
    }
}
