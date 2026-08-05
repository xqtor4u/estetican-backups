<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class BookingRescheduleGuardTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function booking(string $status): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => $status,
            'total_estimated_price' => 0,
        ]);
    }

    public function test_cannot_reschedule_a_booking_that_already_started(): void
    {
        $booking = $this->booking('work_order');
        $originalScheduledAt = $booking->scheduled_at;
        $operator = $booking->operator;

        $response = $this->actingAs($this->admin())->put(route('agenda.update', $booking), [
            'scheduled_at' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'operator_id' => $operator->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertTrue($originalScheduledAt->equalTo($booking->scheduled_at));
    }

    public function test_can_still_reschedule_a_booking_that_has_not_started(): void
    {
        $booking = $this->booking('scheduled');
        $operator = $booking->operator;
        $newDate = now()->addDay()->setTime(11, 0);

        $response = $this->actingAs($this->admin())->put(route('agenda.update', $booking), [
            'scheduled_at' => $newDate->format('Y-m-d H:i:s'),
            'operator_id' => $operator->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertTrue($newDate->equalTo($booking->scheduled_at));
    }
}
