<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\OperatorCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * La pantalla de Caja del móvil, sin sesión abierta, solo decía "Abre la sesión desde el
 * backoffice de finanzas" — sin ninguna forma de resolverlo ahí. CashController::openSession()
 * es el endpoint nuevo que lo permite directo desde la app, resolviendo la caja a partir del
 * check-in activo del usuario (mismo lock que CashSessionController::store() del lado web,
 * SYNC-011, para evitar dos sesiones abiertas simultáneas).
 */
class CashOpenSessionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createAdminUser();
    }

    private function authHeader(): array
    {
        return $this->createAdminAuthHeader($this->user);
    }

    private function branchWithRegister(): array
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Sucursal '.uniqid()]);
        $register = CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja principal']);

        return [$branch, $register];
    }

    public function test_opens_a_session_using_the_branch_from_the_active_checkin(): void
    {
        [$branch, $register] = $this->branchWithRegister();
        OperatorCheckin::create(['user_id' => $this->user->id, 'branch_id' => $branch->id, 'checked_in_at' => now()]);

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/cash/session', ['opening_amount' => 150]);

        $response->assertOk();
        $response->assertJsonPath('status', 'active');
        $response->assertJsonPath('session.opening_amount', 150);
        $this->assertSame(1, CashSession::where('cash_register_id', $register->id)->where('status', 'abierta')->count());
    }

    public function test_requires_an_active_checkin(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/cash/session', ['opening_amount' => 100]);

        $response->assertStatus(422);
        $this->assertSame(0, CashSession::count());
    }

    public function test_cannot_open_a_second_session_when_one_is_already_active(): void
    {
        [$branch] = $this->branchWithRegister();
        OperatorCheckin::create(['user_id' => $this->user->id, 'branch_id' => $branch->id, 'checked_in_at' => now()]);

        $this->withHeaders($this->authHeader())
            ->postJson('/api/cash/session', ['opening_amount' => 100])
            ->assertOk();

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/cash/session', ['opening_amount' => 200]);

        $response->assertStatus(409);
        $this->assertSame(1, CashSession::count());
        $this->assertSame(100.0, (float) CashSession::first()->opening_amount);
    }
}
