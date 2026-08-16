<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Corrección de diseño pedida por Tomas (15-16/08/2026): check-in y Caja son dominios
 * independientes (RH vs. Finanzas). El alcance de Caja ahora se resuelve por `branch_id`
 * asignado al usuario + permisos granulares (`caja.ver`, `caja.movimientos.crear`, etc.),
 * nunca por check-in. Este test cubre el scoping por sucursal y el flujo de reversión de
 * movimientos (nunca borrado real — cada movimiento tiene un asiento contable real detrás).
 */
class CashMovementsBranchScopingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    protected function setUp(): void
    {
        parent::setUp();
        // account_id=6 es CAJA_ACCOUNT_ID (constante fija en CashController, ligada al plan de
        // cuentas real) — la FK de journal_entry_lines exige que exista de verdad.
        Account::forceCreate(['id' => 6, 'code' => '1100', 'name' => 'Caja', 'type' => 'activo', 'allows_entries' => true]);
    }

    private function counterpartAccount(): Account
    {
        return Account::create(['code' => '5100', 'name' => 'Gastos generales', 'type' => 'gasto', 'allows_entries' => true]);
    }

    private function operatorWithBranch(?int $branchId, array $permissions = ['caja.ver', 'caja.movimientos.crear']): User
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

    private function openSessionFor(Branch $branch): CashSession
    {
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja principal']);

        return CashSession::create([
            'cash_register_id' => $register->id,
            'branch_id' => $branch->id,
            'opened_by_user_id' => $this->createAdminUser()->id,
            'opened_at' => now(),
            'opening_amount' => 100,
            'status' => 'abierta',
        ]);
    }

    private function movementFor(CashSession $session, string $direction = 'entrada', float $amount = 50): CashMovement
    {
        $entry = JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'description' => 'Movimiento de prueba',
            'status' => 'aplicado',
            'branch_id' => $session->branch_id,
            'created_by_user_id' => $this->createAdminUser()->id,
            'posted_at' => now(),
        ]);

        $counterpart = $this->counterpartAccount();

        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => 6, 'debit' => $amount, 'credit' => 0, 'description' => 'x']);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $counterpart->id, 'debit' => 0, 'credit' => $amount, 'description' => 'x']);

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'type' => 'entrada',
            'direction' => $direction,
            'amount' => $amount,
            'concept' => 'Concepto original',
            'counterpart_account_id' => $counterpart->id,
            'journal_entry_id' => $entry->id,
            'created_by_user_id' => $this->createAdminUser()->id,
        ]);
    }

    // ── Scoping por sucursal ──────────────────────────────────────────────

    public function test_user_without_assigned_branch_cannot_view_session_state(): void
    {
        $user = $this->operatorWithBranch(null);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))->getJson('/api/cash/session');

        $response->assertOk();
        $response->assertJsonPath('status', 'no_branch');
    }

    public function test_user_without_assigned_branch_cannot_list_movements(): void
    {
        $user = $this->operatorWithBranch(null);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))->getJson('/api/cash/movements');

        $response->assertStatus(422);
    }

    public function test_operator_cannot_register_a_movement_on_another_branchs_session(): void
    {
        $ownBranch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Propia']);
        $otherBranch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Otra']);
        $otherSession = $this->openSessionFor($otherBranch);
        $user = $this->operatorWithBranch($ownBranch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/sessions/{$otherSession->id}/movements", [
                'type' => 'entrada',
                'amount' => 50,
                'concept' => 'Intento cruzado',
                'counterpart_account_id' => $this->counterpartAccount()->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_active_checkin_in_a_different_branch_does_not_grant_access(): void
    {
        // El check-in NO debe influir en absoluto — este operador tiene su sucursal asignada en
        // A, pero hizo check-in físico en B (visitando esa sucursal); su acceso a Caja sigue
        // siendo el de A únicamente.
        $branchA = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'A']);
        $branchB = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'B']);
        $sessionB = $this->openSessionFor($branchB);
        $user = $this->operatorWithBranch($branchA->id);

        \App\Models\OperatorCheckin::create([
            'user_id' => $user->id,
            'branch_id' => $branchB->id,
            'checked_in_at' => now(),
        ]);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/sessions/{$sessionB->id}/movements", [
                'type' => 'entrada',
                'amount' => 50,
                'concept' => 'No debería poder',
                'counterpart_account_id' => $this->counterpartAccount()->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_operate_any_branch(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Cualquiera']);
        $session = $this->openSessionFor($branch);
        $admin = $this->createAdminUser();

        $response = $this->withHeaders($this->createAdminAuthHeader($admin))
            ->postJson("/api/cash/sessions/{$session->id}/movements", [
                'type' => 'entrada',
                'amount' => 50,
                'concept' => 'Super-admin en cualquier sucursal',
                'counterpart_account_id' => $this->counterpartAccount()->id,
            ]);

        $response->assertStatus(201);
    }

    // ── Editar movimiento (solo concepto/notas) ───────────────────────────

    public function test_editing_a_movement_only_changes_concept_and_notes(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $session = $this->openSessionFor($branch);
        $movement = $this->movementFor($session, 'entrada', 50);
        $user = $this->operatorWithBranch($branch->id, ['caja.ver', 'caja.movimientos.editar']);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->patchJson("/api/cash/movements/{$movement->id}", [
                'concept' => 'Concepto corregido',
                'notes' => 'nota nueva',
            ]);

        $response->assertOk();
        $response->assertJsonPath('concept', 'Concepto corregido');
        $movement->refresh();
        $this->assertSame(50.0, (float) $movement->amount);
        $this->assertSame('entrada', $movement->direction);
    }

    public function test_cannot_edit_a_movement_from_another_branch(): void
    {
        $ownBranch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Propia']);
        $otherBranch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Otra']);
        $otherSession = $this->openSessionFor($otherBranch);
        $movement = $this->movementFor($otherSession);
        $user = $this->operatorWithBranch($ownBranch->id, ['caja.ver', 'caja.movimientos.editar']);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->patchJson("/api/cash/movements/{$movement->id}", ['concept' => 'x']);

        $response->assertStatus(403);
    }

    public function test_cannot_edit_a_movement_from_a_closed_session(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $session = $this->openSessionFor($branch);
        $movement = $this->movementFor($session);
        $user = $this->operatorWithBranch($branch->id, ['caja.ver', 'caja.movimientos.editar']);
        $session->update(['status' => 'cerrada']);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->patchJson("/api/cash/movements/{$movement->id}", ['concept' => 'x']);

        $response->assertStatus(422);
        $movement->refresh();
        $this->assertSame('Concepto original', $movement->concept);
    }

    // ── Revertir movimiento (nunca borrar) ────────────────────────────────

    public function test_reverting_a_movement_creates_an_opposite_one_and_nets_to_zero(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $session = $this->openSessionFor($branch);
        $movement = $this->movementFor($session, 'entrada', 80);
        $user = $this->operatorWithBranch($branch->id, ['caja.ver', 'caja.movimientos.revertir']);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/movements/{$movement->id}/revert");

        $response->assertStatus(201);
        $response->assertJsonPath('direction', 'salida');
        $response->assertJsonPath('amount', 80);

        $movement->refresh();
        $this->assertNotNull($movement->reversed_at);
        $this->assertSame(2, CashMovement::where('cash_session_id', $session->id)->count());

        $totalEntradas = CashMovement::where('cash_session_id', $session->id)->where('direction', 'entrada')->sum('amount');
        $totalSalidas  = CashMovement::where('cash_session_id', $session->id)->where('direction', 'salida')->sum('amount');
        $this->assertSame($totalEntradas, $totalSalidas);
    }

    public function test_cannot_revert_the_same_movement_twice(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $session = $this->openSessionFor($branch);
        $movement = $this->movementFor($session);
        $user = $this->operatorWithBranch($branch->id, ['caja.ver', 'caja.movimientos.revertir']);

        $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/movements/{$movement->id}/revert")
            ->assertStatus(201);

        $second = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/movements/{$movement->id}/revert");

        $second->assertStatus(409);
    }

    public function test_cannot_revert_a_reversal(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $session = $this->openSessionFor($branch);
        $movement = $this->movementFor($session);
        $user = $this->operatorWithBranch($branch->id, ['caja.ver', 'caja.movimientos.revertir']);

        $reversalId = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/movements/{$movement->id}/revert")
            ->json('id');

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/movements/{$reversalId}/revert");

        $response->assertStatus(422);
    }

    public function test_cannot_revert_a_movement_from_a_closed_session(): void
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'X']);
        $session = $this->openSessionFor($branch);
        $movement = $this->movementFor($session);
        $user = $this->operatorWithBranch($branch->id, ['caja.ver', 'caja.movimientos.revertir']);
        $session->update(['status' => 'cerrada']);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/movements/{$movement->id}/revert");

        $response->assertStatus(422);
        $this->assertNull($movement->refresh()->reversed_at);
        $this->assertSame(1, CashMovement::where('cash_session_id', $session->id)->count());
    }
}
