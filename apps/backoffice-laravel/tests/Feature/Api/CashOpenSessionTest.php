<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * La pantalla de Caja del móvil, sin sesión abierta, solo decía "Abre la sesión desde el
 * backoffice de finanzas" — sin ninguna forma de resolverlo ahí. CashController::openSession()
 * es el endpoint nuevo que lo permite directo desde la app.
 *
 * La sucursal se resuelve por `branch_id` asignado al usuario (dato organizacional persistente,
 * ver migración `add_branch_id_to_users_table`) — NUNCA por check-in, que es solo asistencia sin
 * relación con autorización (corrección de diseño pedida por Tomas, 15-16/08/2026, ver
 * `resolveBranchId()` en `CashController`). Un super-admin, sin sucursal propia asignada,
 * elige explícitamente `branch_id` en el request.
 */
class CashOpenSessionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function branchWithRegister(): array
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Sucursal '.uniqid()]);
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja principal']);

        return [$branch, $register];
    }

    private function operatorWithBranch(?int $branchId): User
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
        $user->givePermissionTo('caja.abrir', 'caja.ver');

        return $user;
    }

    public function test_opens_a_session_using_the_branch_assigned_to_the_user(): void
    {
        [$branch, $register] = $this->branchWithRegister();
        $user = $this->operatorWithBranch($branch->id);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/session', ['opening_amount' => 150]);

        $response->assertOk();
        $response->assertJsonPath('status', 'active');
        $response->assertJsonPath('session.opening_amount', 150);
        $this->assertSame(1, CashSession::where('cash_register_id', $register->id)->where('status', 'abierta')->count());
    }

    public function test_requires_a_branch_assigned_to_the_user(): void
    {
        $user = $this->operatorWithBranch(null);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/session', ['opening_amount' => 100]);

        $response->assertStatus(422);
        $this->assertSame(0, CashSession::count());
    }

    public function test_cannot_open_a_second_session_when_one_is_already_active(): void
    {
        [$branch] = $this->branchWithRegister();
        $user = $this->operatorWithBranch($branch->id);

        $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/session', ['opening_amount' => 100])
            ->assertOk();

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/session', ['opening_amount' => 200]);

        $response->assertStatus(409);
        $this->assertSame(1, CashSession::count());
        $this->assertSame(100.0, (float) CashSession::first()->opening_amount);
    }

    public function test_active_checkin_has_no_bearing_on_opening_a_session(): void
    {
        // Un check-in activo en una sucursal DISTINTA de la asignada no debe cambiar nada — el
        // check-in es asistencia (RH), no autorización de Caja (Finanzas).
        [$assignedBranch, $assignedRegister] = $this->branchWithRegister();
        [$checkinBranch] = $this->branchWithRegister();
        $user = $this->operatorWithBranch($assignedBranch->id);

        \App\Models\OperatorCheckin::create([
            'user_id' => $user->id,
            'branch_id' => $checkinBranch->id,
            'checked_in_at' => now(),
        ]);

        $response = $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/cash/session', ['opening_amount' => 75]);

        $response->assertOk();
        $this->assertSame(1, CashSession::where('cash_register_id', $assignedRegister->id)->count());
    }

    public function test_super_admin_must_choose_a_branch_explicitly(): void
    {
        $admin = $this->createAdminUser();

        $withoutBranch = $this->withHeaders($this->createAdminAuthHeader($admin))
            ->postJson('/api/cash/session', ['opening_amount' => 100]);
        $withoutBranch->assertStatus(422);

        [$branch, $register] = $this->branchWithRegister();
        $withBranch = $this->withHeaders($this->createAdminAuthHeader($admin))
            ->postJson('/api/cash/session', ['opening_amount' => 100, 'branch_id' => $branch->id]);

        $withBranch->assertOk();
        $this->assertSame(1, CashSession::where('cash_register_id', $register->id)->count());
    }
}
