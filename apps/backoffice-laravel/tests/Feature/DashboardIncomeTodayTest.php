<?php

namespace Tests\Feature;

use App\Models\BankLedger;
use App\Models\CashLedger;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * "Ingresos del día" del Dashboard solo sumaba CashLedger/BankLedger (el camino vía
 * Quote aceptado) — nunca incluía Payment, el modelo real que usa el cobro directo
 * desde la app móvil sin presupuesto de por medio (Api/PaymentController::store()).
 * Mismo tipo de bug ya arreglado una vez en el saldo de citas de Agenda, nunca portado
 * al Dashboard. CashSessionController::allPaymentsForPeriod() ya mezcla las 3 fuentes.
 */
class DashboardIncomeTodayTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser(['role' => 'admin']);
    }

    public function test_income_today_includes_mobile_payments_without_a_quote(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);

        CashLedger::create([
            'client_id' => $client->id,
            'payable_type' => Client::class,
            'payable_id' => $client->id,
            'amount' => 100,
            'payment_method' => 'efectivo',
        ]);
        BankLedger::create([
            'client_id' => $client->id,
            'payable_type' => Client::class,
            'payable_id' => $client->id,
            'amount' => 50,
            'payment_method' => 'tarjeta',
        ]);
        Payment::create(['client_id' => $client->id, 'amount' => 75, 'payment_method' => 'efectivo']);

        $response = $this->actingAs($this->admin())->get(route('dashboard.index'));

        $response->assertOk();
        // 100 (caja) + 50 (banco) + 75 (pago móvil directo) = 225
        $response->assertSee('225.00');
    }
}
