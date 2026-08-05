<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class BookingUnfulfillableTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
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

    public function test_a_scheduled_booking_can_be_marked_unfulfillable_with_a_reason(): void
    {
        $booking = $this->bookingWithStatus('scheduled');

        $response = $this->actingAs($this->admin())->post(route('agenda.unfulfillable', $booking), [
            'reason' => 'El animal no cooperó',
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame('unfulfillable', $booking->status);
        $this->assertSame('El animal no cooperó', $booking->cancellation_reason);
    }

    public function test_a_work_order_booking_can_be_marked_unfulfillable_with_a_reason(): void
    {
        $booking = $this->bookingWithStatus('work_order');

        $response = $this->actingAs($this->admin())->post(route('agenda.unfulfillable', $booking), [
            'reason' => 'El groomer se lastimó',
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame('unfulfillable', $booking->status);
        $this->assertSame('El groomer se lastimó', $booking->cancellation_reason);
    }

    public function test_reason_is_optional(): void
    {
        $booking = $this->bookingWithStatus('work_order');

        $response = $this->actingAs($this->admin())->post(route('agenda.unfulfillable', $booking));

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame('unfulfillable', $booking->status);
        $this->assertNull($booking->cancellation_reason);
    }

    public function test_a_completed_booking_cannot_be_marked_unfulfillable(): void
    {
        $booking = $this->bookingWithStatus('completed');

        $this->actingAs($this->admin())->post(route('agenda.unfulfillable', $booking));

        $booking->refresh();
        $this->assertSame('completed', $booking->status);
    }

    public function test_no_show_now_stores_the_optional_reason(): void
    {
        $booking = $this->bookingWithStatus('scheduled');

        $response = $this->actingAs($this->admin())->post(route('agenda.no-show', $booking), [
            'reason' => 'No avisó, no contestó llamadas',
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame('no_show', $booking->status);
        $this->assertSame('No avisó, no contestó llamadas', $booking->cancellation_reason);
    }

    public function test_a_work_order_booking_can_also_be_marked_no_show(): void
    {
        // El cliente puede "no haber asistido" incluso si el staff ya había marcado
        // la cita como En proceso — el flag de falta del cliente no debe depender
        // del estado técnico interno de la reserva.
        $booking = $this->bookingWithStatus('work_order');

        $response = $this->actingAs($this->admin())->post(route('agenda.no-show', $booking), [
            'reason' => 'Se fue a mitad del servicio',
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame('no_show', $booking->status);
        $this->assertSame('Se fue a mitad del servicio', $booking->cancellation_reason);
    }
}
