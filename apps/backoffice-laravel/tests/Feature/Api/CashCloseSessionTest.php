<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use Database\Seeders\BaseRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * ZEUS-034: cerrar el turno de caja directo desde la app móvil — antes esto solo era posible
 * desde el backoffice web (`Finanzas → Cajas → Cerrar`, `CashSessionController::doClose()`), la
 * pantalla de Caja del móvil no tenía ninguna forma de resolverlo. `CashController::closeSession()`
 * es un espejo exacto de `doClose()`: mismo cálculo de `expected`/`difference` vía
 * `CashSessionExpectedAmountService::expectedAmount()` (única fuente de verdad, compartida con
 * el web).
 */
class CashCloseSessionTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function branchWithRegister(): array
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Sucursal '.uniqid()]);
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja principal']);

        return [$branch, $register];
    }

    private function operatorWithBranch(?int $branchId): User
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
        $user->givePermissionTo('caja.cerrar', 'caja.ver');

        return $user;
    }

    private function openSession(CashRegister $register, User $user, float $opening = 100): CashSession
    {
        return CashSession::create([
            'cash_register_id' => $register->id,
            'branch_id' => $register->branch_id,
            'opened_by_user_id' => $user->id,
            'opened_at' => now()->subHour(),
            'opening_amount' => $opening,
            'status' => 'abierta',
        ]);
    }

    public function test_closes_a_session_with_exact_closing_amount(): void
    {
        [, $register] = $this->branchWithRegister();
        $user = $this->operatorWithBranch($register->branch_id);
        $session = $this->openSession($register, $user, 100);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/sessions/{$session->id}/close", ['closing_amount' => 100]);

        $response->assertOk();
        $response->assertJsonPath('difference', 0);
        $session->refresh();
        $this->assertSame('cerrada', $session->status);
        $this->assertNotNull($session->closed_at);
        $this->assertSame($user->id, $session->closed_by_user_id);
    }

    public function test_reports_a_faltante_when_closing_amount_is_below_expected(): void
    {
        [, $register] = $this->branchWithRegister();
        $user = $this->operatorWithBranch($register->branch_id);
        $session = $this->openSession($register, $user, 100);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/sessions/{$session->id}/close", ['closing_amount' => 90]);

        $response->assertOk();
        $response->assertJsonPath('difference', -10);
    }

    public function test_reports_a_sobrante_when_closing_amount_is_above_expected(): void
    {
        [, $register] = $this->branchWithRegister();
        $user = $this->operatorWithBranch($register->branch_id);
        $session = $this->openSession($register, $user, 100);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/sessions/{$session->id}/close", ['closing_amount' => 120]);

        $response->assertOk();
        $response->assertJsonPath('difference', 20);
    }

    public function test_cannot_close_an_already_closed_session(): void
    {
        [, $register] = $this->branchWithRegister();
        $user = $this->operatorWithBranch($register->branch_id);
        $session = $this->openSession($register, $user);

        $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/sessions/{$session->id}/close", ['closing_amount' => 100])
            ->assertOk();

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/sessions/{$session->id}/close", ['closing_amount' => 100]);

        $response->assertStatus(422);
    }

    public function test_rejects_closing_a_session_from_a_different_branch(): void
    {
        [, $register] = $this->branchWithRegister();
        [, $otherRegister] = $this->branchWithRegister();
        $user = $this->operatorWithBranch($otherRegister->branch_id);
        $session = $this->openSession($register, $user);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/sessions/{$session->id}/close", ['closing_amount' => 100]);

        $response->assertStatus(403);
        $this->assertSame('abierta', $session->fresh()->status);
    }

    public function test_super_admin_can_close_a_session_from_any_branch(): void
    {
        [, $register] = $this->branchWithRegister();
        $admin = $this->createAdminUser();
        $session = $this->openSession($register, $admin, 100);

        $response = $this->withHeaders($this->createAdminAuthHeader($admin))
            ->postJson("/api/cash/sessions/{$session->id}/close", ['closing_amount' => 100]);

        $response->assertOk();
        $this->assertSame('cerrada', $session->fresh()->status);
    }

    public function test_requires_the_caja_cerrar_permission(): void
    {
        [, $register] = $this->branchWithRegister();
        $user = $this->operatorWithBranch($register->branch_id);
        $user->revokePermissionTo('caja.cerrar');
        $session = $this->openSession($register, $user);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson("/api/cash/sessions/{$session->id}/close", ['closing_amount' => 100]);

        $response->assertForbidden();
        $this->assertSame('abierta', $session->fresh()->status);
    }
}
