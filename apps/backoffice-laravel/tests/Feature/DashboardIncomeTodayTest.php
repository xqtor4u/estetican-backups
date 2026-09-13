<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-098: "Ingresos del día" del Dashboard suma los Payment de hoy (tabla única canónica
 * de cobro, móvil y web), desglosados por destino caja/banco. Antes leía además
 * cash_ledgers/bank_ledgers como fuentes paralelas del camino web.
 */
class DashboardIncomeTodayTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser(['role' => 'admin']);
    }

    public function test_income_today_sums_todays_payments_by_destination(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);

        Payment::create(['client_id' => $client->id, 'amount' => 100, 'payment_method' => 'Efectivo', 'destination' => 'caja']);
        Payment::create(['client_id' => $client->id, 'amount' => 50, 'payment_method' => 'Tarjeta', 'destination' => 'banco']);
        Payment::create(['client_id' => $client->id, 'amount' => 75, 'payment_method' => 'Efectivo', 'destination' => 'caja']);

        // Un pago de ayer no debe contar en "hoy".
        $old = Payment::create(['client_id' => $client->id, 'amount' => 999, 'payment_method' => 'Efectivo', 'destination' => 'caja']);
        $old->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        $response = $this->actingAs($this->admin())->get(route('dashboard.index'));

        $response->assertOk();
        // Total: 100 + 50 + 75 = 225 ; Caja: 100 + 75 = 175 ; Banco: 50
        $response->assertSee('225.00');
        $response->assertSee('175.00');
    }
}
