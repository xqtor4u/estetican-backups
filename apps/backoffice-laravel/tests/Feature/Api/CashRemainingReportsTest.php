<?php

namespace Tests\Feature\Api;

use App\Mail\CashCierresMail;
use App\Mail\CashPendientesMail;
use App\Mail\CashPorOperadorMail;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Los últimos 3 reportes del primer corte móvil de Finanzas: "Por operador", "Pendientes por
 * cobrar" (el único que NO sale de movements()/resolveMovementsItems — reusa
 * SpaBooking::unpaidBalance() directo) y "Cierre de turno" (lee cash_sessions ya cerradas,
 * nunca recalcula).
 */
class CashRemainingReportsTest extends TestCase
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

    // ── Por operador ────────────────────────────────────────────────────

    public function test_por_operador_separates_entradas_and_salidas_per_operator(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja principal']);
        $session = CashSession::create([
            'cash_register_id' => $register->id, 'branch_id' => $branch->id,
            'opened_by_user_id' => $this->createAdminUser()->id, 'opened_at' => now(),
            'opening_amount' => 0, 'status' => 'abierta',
        ]);
        $counterpart = Account::create(['code' => '5100', 'name' => 'Gastos generales', 'type' => 'gasto', 'allows_entries' => true]);
        $creator = $this->createAdminUser(['name' => 'Creador Uno']);
        $entry = JournalEntry::create([
            'entry_date' => now()->toDateString(), 'description' => 'x', 'status' => 'aplicado',
            'branch_id' => $branch->id, 'created_by_user_id' => $creator->id, 'posted_at' => now(),
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => 6, 'debit' => 50, 'credit' => 0, 'description' => 'x']);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $counterpart->id, 'debit' => 0, 'credit' => 50, 'description' => 'x']);
        CashMovement::create([
            'cash_session_id' => $session->id, 'type' => 'entrada', 'direction' => 'entrada', 'amount' => 50,
            'concept' => 'x', 'counterpart_account_id' => $counterpart->id, 'journal_entry_id' => $entry->id,
            'created_by_user_id' => $creator->id,
        ]);

        $viewer = $this->operatorWithBranch(null);
        $viewer->update(['role' => 'admin']);
        $viewer->assignRole('admin');

        $response = $this->withHeaders($this->createAdminAuthHeader($viewer))
            ->getJson('/api/cash/reports/por-operador?date_from=2020-01-01&date_to=2030-01-01');

        $response->assertOk();
        $response->assertJsonPath('byOperator.0.name', 'Creador Uno');
        $response->assertJsonPath('byOperator.0.totalEntradas', 50);
        $response->assertJsonPath('byOperator.0.totalSalidas', 0);
    }

    public function test_por_operador_pdf_returns_a_pdf_download(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))->get('/api/cash/reports/por-operador/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_por_operador_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/reports/por-operador/email', ['email' => 'destino@example.com']);

        $response->assertOk();
        Mail::assertSent(CashPorOperadorMail::class, fn (CashPorOperadorMail $mail) => $mail->hasTo('destino@example.com'));
    }

    // ── Pendientes por cobrar ──────────────────────────────────────────

    private function bookingWithUnpaidBalance(string $status, float $price): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Lopez', 'email' => 'ana-'.uniqid().'@example.com']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Nina', 'species' => 'perro']);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->subHour(),
            'duration_minutes' => 60,
            'status' => $status,
            'total_estimated_price' => $price,
        ]);
    }

    public function test_pendientes_only_includes_unpaid_work_order_and_completed_bookings(): void
    {
        $this->bookingWithUnpaidBalance('work_order', 150);
        $this->bookingWithUnpaidBalance('completed', 200);
        $this->bookingWithUnpaidBalance('scheduled', 300); // no cuenta: ni terminada ni en proceso
        $this->bookingWithUnpaidBalance('cancelled', 400); // no cuenta: cancelada nunca es "pendiente"

        $user = $this->operatorWithBranch(null);
        $user->update(['role' => 'admin']);
        $user->assignRole('admin');

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->getJson('/api/cash/reports/pendientes');

        $response->assertOk();
        $response->assertJsonCount(2, 'items');
        $response->assertJsonPath('totalPendiente', 350);
    }

    public function test_pendientes_pdf_returns_a_pdf_download(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))->get('/api/cash/reports/pendientes/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pendientes_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/reports/pendientes/email', ['email' => 'destino@example.com']);

        $response->assertOk();
        Mail::assertSent(CashPendientesMail::class, fn (CashPendientesMail $mail) => $mail->hasTo('destino@example.com'));
    }

    // ── Cierre de turno ────────────────────────────────────────────────

    public function test_cierres_only_lists_closed_sessions_with_the_real_persisted_values(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja principal']);
        $admin = $this->createAdminUser();

        CashSession::create([
            'cash_register_id' => $register->id, 'branch_id' => $branch->id,
            'opened_by_user_id' => $admin->id, 'opened_at' => now()->subHours(3), 'opening_amount' => 100,
            'closed_by_user_id' => $admin->id, 'closed_at' => now(), 'status' => 'cerrada',
            'closing_amount' => 195, 'expected_amount' => 200, 'difference' => -5,
        ]);
        // Sesión abierta — nunca debe aparecer en "Cierre de turno".
        CashSession::create([
            'cash_register_id' => $register->id, 'branch_id' => $branch->id,
            'opened_by_user_id' => $admin->id, 'opened_at' => now(), 'opening_amount' => 50, 'status' => 'abierta',
        ]);

        $viewer = $this->operatorWithBranch(null);
        $viewer->update(['role' => 'admin']);
        $viewer->assignRole('admin');

        $response = $this->withHeaders($this->createAdminAuthHeader($viewer))
            ->getJson('/api/cash/reports/cierres?date_from=2020-01-01&date_to=2030-01-01');

        $response->assertOk();
        $response->assertJsonCount(1, 'items');
        $response->assertJsonPath('items.0.difference', -5);
        $response->assertJsonPath('totalDifference', -5);
    }

    public function test_cierres_pdf_returns_a_pdf_download(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))->get('/api/cash/reports/cierres/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_cierres_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/reports/cierres/email', ['email' => 'destino@example.com']);

        $response->assertOk();
        Mail::assertSent(CashCierresMail::class, fn (CashCierresMail $mail) => $mail->hasTo('destino@example.com'));
    }
}
