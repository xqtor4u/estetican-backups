<?php

namespace Tests\Feature\Api;

use App\Mail\CashMetodosPagoMail;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * "Métodos de pago" — desglosa solo cobros (Payment/cash_ledgers/bank_ledgers) por el
 * `payment_method` real, nunca movimientos manuales de CashMovement (ver "Resumen de caja"
 * para esos). Cubre en particular el bug real encontrado el 16/08/2026: dos cobros con el
 * mismo método pero distinta capitalización ("Efectivo"/"efectivo") se agrupaban en filas
 * separadas — MySQL ya los trata como iguales por el collation por defecto, pero un
 * `groupBy()` de PHP sobre el string crudo no.
 */
class CashMetodosPagoReportTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Account::forceCreate(['id' => 6, 'code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
    }

    private function operatorWithBranch(?int $branchId, array $permissions = ['caja.ver']): User
    {
        (new \Database\Seeders\BaseRolesSeeder())->run();

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
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_metodos_pago_normalizes_casing_of_the_same_payment_method(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Lopez', 'email' => 'ana@example.com']);
        Payment::create(['client_id' => $client->id, 'amount' => 250, 'destination' => 'caja', 'payment_method' => 'Efectivo']);
        Payment::create(['client_id' => $client->id, 'amount' => 321.50, 'destination' => 'caja', 'payment_method' => 'efectivo']);

        $user = $this->operatorWithBranch(null);
        $user->update(['role' => 'admin']);
        $user->assignRole('admin');

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->getJson('/api/cash/reports/metodos-pago?date_from=2020-01-01&date_to=2030-01-01');

        $response->assertOk();
        $response->assertJsonCount(1, 'byMethod');
        $response->assertJsonPath('byMethod.0.method', 'Efectivo');
        $response->assertJsonPath('byMethod.0.count', 2);
        $response->assertJsonPath('totalCobrado', 571.5);
    }

    public function test_metodos_pago_pdf_returns_a_pdf_download(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->get('/api/cash/reports/metodos-pago/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_metodos_pago_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/reports/metodos-pago/email', ['email' => 'destino@example.com']);

        $response->assertOk();
        Mail::assertSent(CashMetodosPagoMail::class, fn (CashMetodosPagoMail $mail) => $mail->hasTo('destino@example.com'));
    }
}
