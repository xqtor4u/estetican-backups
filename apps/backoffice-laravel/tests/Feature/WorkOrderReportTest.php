<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Orden',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Orden',
            'email' => 'admin-orden-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function bookingWithoutQuote(): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño y corte', 'type' => 'grooming', 'price' => 350, 'duration_minutes' => 60, 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => 'completed',
            'total_estimated_price' => 350,
            'notes' => 'Nota tomada al agendar',
        ]);

        SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'current_price' => 350,
        ]);

        $booking->processNotes()->create(['note' => 'Se aplicó producto hipoalergénico']);

        return $booking;
    }

    public function test_work_order_lists_booking_services_when_there_is_no_quote(): void
    {
        $booking = $this->bookingWithoutQuote();

        $response = $this->actingAs($this->admin())->get(route('reports.work-order', $booking));

        $response->assertOk();
        $response->assertSee('Baño y corte');
        $response->assertSee($booking->operator->full_name);
        $response->assertDontSee('No hay servicios aceptados registrados.');
    }

    public function test_work_order_shows_booking_notes_and_process_notes(): void
    {
        $booking = $this->bookingWithoutQuote();

        $response = $this->actingAs($this->admin())->get(route('reports.work-order', $booking));

        $response->assertOk();
        $response->assertSee('Nota tomada al agendar');
        $response->assertSee('Se aplicó producto hipoalergénico');
    }
}
