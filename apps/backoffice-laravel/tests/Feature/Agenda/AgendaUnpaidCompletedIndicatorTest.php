<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * El usuario reportó que una cita "Completada" no dice si ya se cobró. Investigando
 * se encontró un bug real de fondo: la columna "Total" de la tabla de Día solo sumaba
 * CashLedger/BankLedger vía presupuesto aceptado — para una cita cobrada desde móvil
 * (Payment directo, sin Quote de por medio, el camino más usado en producción real)
 * el saldo siempre se mostraba como el precio completo sin pagar, aunque sí estuviera
 * pagada, y nunca se pintaba en rojo. `SpaBooking::totalPaid()`/`unpaidBalance()`
 * ahora suman ambos caminos, y "Completado" gana un asterisco rojo cuando de verdad
 * queda saldo pendiente.
 */
class AgendaUnpaidCompletedIndicatorTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function completedBooking(float $price = 350): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka'.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño y corte', 'type' => 'grooming', 'price' => $price, 'duration_minutes' => 60, 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'completed',
            'total_estimated_price' => $price,
        ]);

        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => $price]);

        return $booking;
    }

    public function test_unpaid_balance_is_full_price_without_any_payment_registered(): void
    {
        $booking = $this->completedBooking(350);

        $this->assertSame(0.0, $booking->totalPaid());
        $this->assertSame(350.0, $booking->unpaidBalance());
    }

    public function test_a_direct_mobile_payment_without_a_quote_correctly_zeroes_the_balance(): void
    {
        $booking = $this->completedBooking(350);

        Payment::create([
            'client_id' => $booking->pet->client_id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => 350,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
            'category' => 'liquidacion',
        ]);

        $booking->refresh();
        $this->assertSame(350.0, $booking->totalPaid());
        $this->assertSame(0.0, $booking->unpaidBalance());
    }

    public function test_day_table_marks_a_completed_unpaid_booking_with_a_red_asterisk(): void
    {
        $booking = $this->completedBooking(350);

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Completado sin registro de pago', $html);
        $this->assertStringContainsString('$350.00', $html);
    }

    public function test_day_table_does_not_mark_a_completed_and_fully_paid_booking(): void
    {
        $booking = $this->completedBooking(350);

        Payment::create([
            'client_id' => $booking->pet->client_id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => 350,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
            'category' => 'liquidacion',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $response->assertOk();
        $response->assertDontSee('Completado sin registro de pago');
    }
}
