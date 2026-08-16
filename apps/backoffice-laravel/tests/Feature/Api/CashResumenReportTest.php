<?php

namespace Tests\Feature\Api;

use App\Mail\CashResumenMail;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * "Resumen de caja" (primer reporte real del hub de Finanzas) — descarga en PDF y envío por
 * correo, ambos reusando `resolveMovementsItems()` (mismos datos que ya sirve `movements()`,
 * sin backend nuevo por fuente de datos).
 */
class CashResumenReportTest extends TestCase
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

    private function movementWithReversal(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja principal']);
        $session = CashSession::create([
            'cash_register_id' => $register->id,
            'branch_id' => $branch->id,
            'opened_by_user_id' => $this->createAdminUser()->id,
            'opened_at' => now(),
            'opening_amount' => 0,
            'status' => 'abierta',
        ]);
        $counterpart = Account::create(['code' => '5100', 'name' => 'Gastos generales', 'type' => 'gasto', 'allows_entries' => true]);
        $entry = JournalEntry::create([
            'entry_date' => now()->toDateString(), 'description' => 'x', 'status' => 'aplicado',
            'branch_id' => $branch->id, 'created_by_user_id' => $this->createAdminUser()->id, 'posted_at' => now(),
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => 6, 'debit' => 33, 'credit' => 0, 'description' => 'x']);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $counterpart->id, 'debit' => 0, 'credit' => 33, 'description' => 'x']);
        $original = CashMovement::create([
            'cash_session_id' => $session->id, 'type' => 'entrada', 'direction' => 'entrada', 'amount' => 33,
            'concept' => 'x', 'counterpart_account_id' => $counterpart->id, 'journal_entry_id' => $entry->id,
            'created_by_user_id' => $this->createAdminUser()->id,
        ]);
        CashMovement::create([
            'cash_session_id' => $session->id, 'type' => 'entrada', 'direction' => 'salida', 'amount' => 33,
            'concept' => 'Reversión: x', 'counterpart_account_id' => $counterpart->id, 'journal_entry_id' => $entry->id,
            'created_by_user_id' => $this->createAdminUser()->id, 'reversal_of_movement_id' => $original->id,
        ]);
    }

    public function test_resumen_pdf_returns_a_pdf_download(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Única']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->get('/api/cash/reports/resumen/pdf?date_from=2020-01-01&date_to=2030-01-01');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_resumen_pdf_requires_caja_ver_permission(): void
    {
        $user = $this->operatorWithBranch(null, []);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->get('/api/cash/reports/resumen/pdf');

        $response->assertStatus(403);
    }

    public function test_resumen_email_requires_a_valid_email(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/reports/resumen/email', ['email' => 'no-es-un-correo']);

        $response->assertStatus(422);
    }

    public function test_resumen_email_sends_a_pdf_attachment(): void
    {
        Mail::fake();
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/reports/resumen/email', [
                'email' => 'destino@example.com',
                'date_from' => '2020-01-01',
                'date_to' => '2030-01-01',
            ]);

        $response->assertOk();
        Mail::assertSent(CashResumenMail::class, function (CashResumenMail $mail) {
            return $mail->hasTo('destino@example.com')
                && str_starts_with($mail->pdfContent, '%PDF');
        });
    }

    public function test_resumen_breakdown_separates_entradas_and_salidas_for_a_reversed_movement(): void
    {
        $this->movementWithReversal();
        $user = $this->operatorWithBranch(null, ['caja.ver']);
        $user->update(['role' => 'admin']);
        $user->assignRole('admin');

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->get('/api/cash/reports/resumen/pdf?date_from=2020-01-01&date_to=2030-01-01');

        $response->assertOk();
        // El PDF real se valida visualmente en la sesión de construcción (ver BITACORA) —
        // acá solo se confirma que no truena armando el desglose con datos que sí cancelan
        // entre entradas y salidas (regresión del bug real encontrado: agrupar solo por
        // `type` sumaba el original y su reversión en la misma fila).
        $response->assertHeader('content-type', 'application/pdf');
    }
}
