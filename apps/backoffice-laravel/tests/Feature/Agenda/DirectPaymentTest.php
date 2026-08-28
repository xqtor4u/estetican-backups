<?php

namespace Tests\Feature\Agenda;

use App\Models\Account;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentSeries;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Liquidación directa contra la cita (sin presupuesto) — el camino que necesitan las citas
 * cerradas por "Iniciar cita" + "Terminar y cobrar" (SYNC-052/053), que nunca tuvieron Quote.
 * Antes el botón "LIQUIDAR SALDO" aparecía pero su modal solo se renderizaba `@if($acceptedQuote)`.
 */
class DirectPaymentTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function seedAccounting(): PaymentMethod
    {
        $account = Account::create(['code' => '4900', 'name' => 'Otros ingresos', 'type' => 'ingreso', 'allows_entries' => true]);
        DocumentSeries::create(['document_type' => 'recibo', 'name' => 'Recibos', 'prefix' => 'REC-', 'next_number' => 1, 'padding' => 4, 'is_active' => true]);

        return PaymentMethod::create(['code' => 'EFE', 'name' => 'Efectivo', 'type' => 'cash', 'account_id' => $account->id, 'is_active' => true]);
    }

    private function completedBookingNoQuote(float $price = 300): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Toby']);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño perro mediano', 'type' => 'spa', 'price' => $price, 'duration_minutes' => 30, 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->subHour(),
            'status' => 'completed',
            'total_estimated_price' => $price,
            'duration_minutes' => 30,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => $price]);

        return $booking;
    }

    public function test_billing_summary_shows_charges_and_a_liquidar_button_without_a_quote(): void
    {
        $this->seedAccounting();
        $booking = $this->completedBookingNoQuote(300);

        $response = $this->actingAs($this->createAdminUser())->get(route('agenda.show', $booking));

        $response->assertOk();
        $response->assertSee('Baño perro mediano');                 // resumen de cargos por línea
        $response->assertSee('300.00');                             // subtotal = suma de líneas
        $response->assertSee('LIQUIDAR SALDO');
        $response->assertSee('id="btnLiquidarSaldo"', false);
        $response->assertSee(json_encode(route('agenda.payments.store', $booking)), false); // el modal apunta al camino directo
    }

    public function test_terminar_y_cobrar_completes_the_booking_and_lands_on_the_billing_modal(): void
    {
        $this->seedAccounting();
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Toby']);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 200, 'duration_minutes' => 30, 'is_active' => true]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id, 'scheduled_at' => now()->subHour(),
            'status' => 'work_order', 'total_estimated_price' => 200, 'duration_minutes' => 30,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 200]);

        // "Terminar y cobrar" del pop-up de la agenda: PUT status=completed + cobrar=1.
        $response = $this->actingAs($this->createAdminUser())
            ->put(route('agenda.update', $booking), ['status' => 'completed', 'cobrar' => '1']);

        $response->assertRedirect(route('agenda.show', ['booking' => $booking, 'cobrar' => 1]));
        $this->assertSame('completed', $booking->fresh()->status);

        // La ficha con ?cobrar=1 trae el script que abre el modal de liquidación en JS plano.
        $this->actingAs($this->createAdminUser())
            ->get(route('agenda.show', ['booking' => $booking, 'cobrar' => 1]))
            ->assertOk()
            ->assertSee('id="modalRegisterFinalPayment"', false)
            ->assertSee("if (document.readyState === 'complete') { openModal(); }", false);
    }

    public function test_direct_payment_creates_a_payment_a_receipt_and_settles_the_balance(): void
    {
        $method = $this->seedAccounting();
        $booking = $this->completedBookingNoQuote(300);

        $response = $this->actingAs($this->createAdminUser())->post(route('agenda.payments.store', $booking), [
            'amount' => 300,
            'payment_method_code' => $method->code,
            'notes' => 'Pago en efectivo al cierre',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment = Payment::where('payable_type', SpaBooking::class)->where('payable_id', $booking->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('300.00', (string) $payment->amount);
        $this->assertSame('caja', $payment->destination);
        $this->assertSame(0.0, (float) $booking->fresh()->unpaidBalance());
        $this->assertTrue(Document::where('documentable_id', $booking->id)->where('documentable_type', SpaBooking::class)->exists());
    }

    public function test_direct_payment_requires_the_cobros_registrar_permission(): void
    {
        $method = $this->seedAccounting();
        $booking = $this->completedBookingNoQuote();
        $user = $this->createAdminUser();
        $user->syncRoles([]);
        $user->syncPermissions([]);

        $this->actingAs($user)->post(route('agenda.payments.store', $booking), [
            'amount' => 100,
            'payment_method_code' => $method->code,
        ])->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_direct_payment_is_rejected_for_a_cancelled_booking(): void
    {
        $method = $this->seedAccounting();
        $booking = $this->completedBookingNoQuote();
        $booking->update(['status' => 'cancelled']);

        $this->actingAs($this->createAdminUser())->post(route('agenda.payments.store', $booking), [
            'amount' => 100,
            'payment_method_code' => $method->code,
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_xhr_direct_payment_returns_json_with_the_receipt_url(): void
    {
        $method = $this->seedAccounting();
        $booking = $this->completedBookingNoQuote(300);

        $response = $this->actingAs($this->createAdminUser())
            ->postJson(route('agenda.payments.store', $booking), [
                'amount' => 300,
                'payment_method_code' => $method->code,
            ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'receipt_url' => route('reports.invoice', $booking),
        ]);
        $this->assertSame(0.0, (float) $booking->fresh()->unpaidBalance());
    }

    public function test_print_receipt_button_only_appears_once_a_receipt_exists(): void
    {
        $method = $this->seedAccounting();
        $booking = $this->completedBookingNoQuote(300);
        $admin = $this->createAdminUser();

        // Antes de cobrar: sin botón de imprimir.
        $this->actingAs($admin)->get(route('agenda.show', $booking))
            ->assertOk()
            ->assertDontSee('IMPRIMIR RECIBO')
            ->assertSee('El recibo se genera al registrar el cobro.');

        // Después de cobrar: aparece.
        $this->actingAs($admin)->postJson(route('agenda.payments.store', $booking), [
            'amount' => 300,
            'payment_method_code' => $method->code,
        ])->assertOk();

        $this->actingAs($admin)->get(route('agenda.show', $booking))
            ->assertOk()
            ->assertSee('IMPRIMIR RECIBO')
            ->assertSee('CUENTA PAGADA');
    }
}
