<?php

namespace Tests\Feature\Finances;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * CashSessionController::store() chequeaba "¿hay sesión abierta?" y creaba la nueva
 * sin lock ni transacción — un check-then-act clásico. Dos requests casi simultáneas
 * sobre la misma caja podían crear dos CashSession con status='abierta'. Fix:
 * CashRegister::lockForUpdate() dentro de DB::transaction(), mismo patrón que
 * AccountingService::getNextFolio() ya usa para folios.
 */
class CashSessionOpeningRaceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function cashRegister(): CashRegister
    {
        $branch = Branch::create(['code' => 'BR'.uniqid(), 'name' => 'Sucursal '.uniqid()]);

        return CashRegister::create(['branch_id' => $branch->id, 'name' => 'Caja principal']);
    }

    public function test_opening_a_session_twice_only_creates_one_active_session(): void
    {
        $register = $this->cashRegister();
        $user = $this->admin();

        $first = $this->actingAs($user)->post(route('finances.cash-sessions.store', $register), [
            'opening_amount' => 100,
        ]);
        $first->assertRedirect();
        $first->assertSessionHas('success');

        $second = $this->actingAs($user)->post(route('finances.cash-sessions.store', $register), [
            'opening_amount' => 200,
        ]);
        $second->assertRedirect();
        $second->assertSessionHas('info');

        $this->assertSame(1, CashSession::where('cash_register_id', $register->id)->where('status', 'abierta')->count());
        $this->assertSame(100.0, (float) CashSession::first()->opening_amount);
    }
}
