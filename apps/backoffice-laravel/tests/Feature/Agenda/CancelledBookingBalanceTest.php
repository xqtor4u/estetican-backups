<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una cita cancelada nunca se llegó a prestar — cancelBooking() no toca
 * total_estimated_price, así que sin este guard unpaidBalance() la seguía viendo
 * como "saldo pendiente" completo en la tabla de Agenda y en AgSpaSho, aunque el
 * servicio jamás ocurrió. Los reportes reales de caja/contabilidad (Dashboard,
 * CashSessionController) ya estaban a salvo de este problema de origen — solo leen
 * transacciones reales (Payment/CashLedger/BankLedger), nunca derivan de
 * total_estimated_price, así que una cita cancelada sin pagos ya no aportaba nada ahí.
 */
class CancelledBookingBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-cancelled-balance-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function cancelledBooking(float $price = 500): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka'.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => $price, 'duration_minutes' => 30, 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'cancelled',
            'total_estimated_price' => $price,
        ]);

        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => $price]);

        return $booking;
    }

    public function test_a_cancelled_booking_never_has_an_unpaid_balance(): void
    {
        $booking = $this->cancelledBooking(500);

        $this->assertSame(0.0, $booking->unpaidBalance());
    }

    public function test_day_table_shows_zero_balance_not_the_full_price_for_a_cancelled_booking(): void
    {
        $booking = $this->cancelledBooking(500);

        $response = $this->actingAs($this->admin())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'today']));

        $response->assertOk();
        $response->assertDontSee('$500.00');
    }
}
