<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AgSpaSho (agenda/show.blade.php) usaba "Paciente" para el nombre de la mascota
 * — inconsistente con el resto del sistema, que siempre dice "Mascota" (encabezados
 * de tabla, reportes, app móvil). El nombre del cliente además no tenía ninguna
 * etiqueta. Mismo bug de saldo que en la tabla de Agenda: la tarjeta "Balance" solo
 * sumaba pagos vía presupuesto aceptado, ignorando Payment directo (móvil).
 */
class AgendaShowLabelsAndBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-agenda-show-labels-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function booking(float $price = 400): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka'.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => $price, 'duration_minutes' => 30, 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'total_estimated_price' => $price,
        ]);

        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => $price]);

        return $booking;
    }

    public function test_the_pet_name_card_says_mascota_not_paciente(): void
    {
        $booking = $this->booking();

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Mascota');
        $response->assertDontSee('Paciente');
    }

    public function test_the_client_name_under_the_pet_is_labeled_cliente(): void
    {
        $booking = $this->booking();

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Cliente:', false);
    }

    public function test_balance_card_reflects_a_direct_mobile_payment_without_a_quote(): void
    {
        $booking = $this->booking(400);

        Payment::create([
            'client_id' => $booking->pet->client_id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => 400,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
            'category' => 'liquidacion',
        ]);

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        // El label existente muestra "anticipo $X · total $Y" en cuanto hay algún pago
        // registrado (diseño previo, sin cambios) — lo que se corrigió es que $totalPaid
        // ahora sí ve el Payment directo de móvil en vez de quedar siempre en $0.
        $response->assertSee('anticipo $400.00', false);
        $response->assertSee('total $400.00', false);
        $response->assertDontSee('Por liquidar');
    }

    public function test_balance_card_still_shows_por_liquidar_when_nothing_was_paid(): void
    {
        $booking = $this->booking(400);

        $response = $this->actingAs($this->admin())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Por liquidar');
    }
}
