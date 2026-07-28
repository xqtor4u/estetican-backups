<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Recibo',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Recibo',
            'email' => 'admin-recibo-test-'.uniqid().'@example.com',
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

        Payment::create([
            'client_id' => $client->id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => 350,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
            'category' => 'liquidacion',
        ]);

        $booking->processNotes()->create(['note' => 'Se aplicó producto hipoalergénico']);

        return $booking;
    }

    public function test_invoice_shows_the_real_total_for_a_booking_paid_without_a_quote(): void
    {
        $booking = $this->bookingWithoutQuote();

        $response = $this->actingAs($this->admin())->get(route('reports.invoice', $booking));

        $response->assertOk();
        $response->assertSee('Baño y corte');
        $response->assertSee('350.00');
        $response->assertDontSee('SALDO PENDIENTE');
        $response->assertSee('TOTAL LIQUIDADO');
        // Botón de imprimir sin onclick= inline — la CSP del proyecto lo bloquea en silencio (ver NT-042).
        $response->assertDontSee('onclick="window.print()"', false);
        $response->assertSee('btn-print-document', false);
    }

    public function test_invoice_shows_booking_notes_and_process_notes(): void
    {
        $booking = $this->bookingWithoutQuote();

        $response = $this->actingAs($this->admin())->get(route('reports.invoice', $booking));

        $response->assertOk();
        $response->assertSee('Nota tomada al agendar');
        $response->assertSee('Se aplicó producto hipoalergénico');
    }
}
