<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Database\Seeders\BaseRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * "Turno actual" (GET /api/cash/session) ya sumaba los cobros reales (`Payment`) al saldo
 * esperado (fix del 16/08/2026), pero la lista de `movements` seguía sin mostrarlos como
 * renglones — el saldo cuadraba pero el operador no podía ver qué cobro lo componía. Hallazgo
 * real (19/08/2026), mismo criterio que ya usa `CashReportService` para la vista por período.
 */
class CashSessionMovementsIncludePaymentsTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function operatorWithBranch(int $branchId): User
    {
        (new BaseRolesSeeder)->run();

        $user = User::create([
            'name' => 'Operador Test '.uniqid(),
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test',
            'email' => 'operador-test-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'operador',
            'is_active' => true,
            'can_login' => true,
            'branch_id' => $branchId,
        ]);
        $user->givePermissionTo('caja.ver');

        return $user;
    }

    public function test_session_movements_include_cash_payments_as_line_items(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Sucursal '.uniqid()]);
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja principal']);
        $user = $this->operatorWithBranch($branch->id);

        $session = CashSession::create([
            'cash_register_id' => $register->id,
            'branch_id' => $branch->id,
            'opened_by_user_id' => $user->id,
            'opened_at' => now()->subHour(),
            'opening_amount' => 200,
            'status' => 'abierta',
        ]);

        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $booking = SpaBooking::create([
            'pet_id' => Pet::create(['client_id' => $client->id, 'name' => 'Firu'])->id,
            'operator_id' => null,
            'created_by_user_id' => $user->id,
            'scheduled_at' => now()->subMinutes(30),
            'duration_minutes' => 30,
            'status' => 'completed',
            'total_estimated_price' => 300,
        ]);

        $payment = Payment::create([
            'client_id' => $client->id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => 300,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
            'category' => 'liquidacion',
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))->getJson('/api/cash/session');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => "p-{$payment->id}",
            'type' => 'cobro_efectivo',
            'direction' => 'entrada',
            'amount' => 300,
        ]);
        $this->assertEquals(500, $response->json('totals.saldo_esperado'));
    }
}
