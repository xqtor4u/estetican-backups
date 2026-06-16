<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CashMovementController extends Controller
{
    // account_id de Caja (código 1100)
    private const CAJA_ACCOUNT_ID = 6;

    // Tipos que reducen el efectivo en caja
    private const SALIDA_TYPES = ['retiro', 'deposito_banco', 'gasto', 'perdida'];

    // Cuentas sugeridas por tipo (para el selector del form)
    public static function counterpartOptions(string $type): array
    {
        return match ($type) {
            'deposito_banco' => Account::where('type', 'activo')
                ->whereBetween('id', [7, 8])   // Bancos + BBVA
                ->orderBy('code')->pluck('name', 'id')->toArray(),
            'retiro', 'gasto' => Account::where('type', 'gasto')
                ->whereNotIn('id', [5])         // excluir la cuenta padre
                ->orderBy('code')->pluck('name', 'id')->toArray(),
            'perdida' => [24 => 'Gastos generales'],
            'entrada' => Account::where('type', 'capital')
                ->whereNotIn('id', [3])
                ->orderBy('code')->pluck('name', 'id')->toArray(),
            default => [],
        };
    }

    public function store(Request $request, CashSession $cashSession): RedirectResponse
    {
        if (! $cashSession->isOpen()) {
            return back()->with('error', 'La sesión ya está cerrada.');
        }

        $type = $request->input('type');

        $validated = $request->validate([
            'type'                  => ['required', 'in:retiro,deposito_banco,gasto,perdida,entrada'],
            'amount'                => ['required', 'numeric', 'min:0.01'],
            'concept'               => ['required', 'string', 'max:255'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'counterpart_account_id'=> ['required', 'exists:accounts,id'],
        ]);

        $direction = in_array($type, self::SALIDA_TYPES) ? 'salida' : 'entrada';

        // Generar póliza contable
        $entry = JournalEntry::create([
            'entry_date'          => now()->toDateString(),
            'description'         => $this->entryDescription($type, $validated['concept']),
            'status'              => 'aplicado',
            'branch_id'           => $cashSession->branch_id,
            'created_by_user_id'  => auth()->id(),
            'posted_by_user_id'   => auth()->id(),
            'posted_at'           => now(),
            'reference_type'      => CashSession::class,
            'reference_id'        => $cashSession->id,
        ]);

        $amount = (float) $validated['amount'];

        if ($direction === 'salida') {
            // DR contrapartida / CR Caja
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $validated['counterpart_account_id'], 'debit' => $amount, 'credit' => 0, 'description' => $validated['concept']]);
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => self::CAJA_ACCOUNT_ID, 'debit' => 0, 'credit' => $amount, 'description' => $validated['concept']]);
        } else {
            // DR Caja / CR contrapartida
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => self::CAJA_ACCOUNT_ID, 'debit' => $amount, 'credit' => 0, 'description' => $validated['concept']]);
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $validated['counterpart_account_id'], 'debit' => 0, 'credit' => $amount, 'description' => $validated['concept']]);
        }

        CashMovement::create([
            'cash_session_id'       => $cashSession->id,
            'type'                  => $type,
            'direction'             => $direction,
            'amount'                => $amount,
            'concept'               => $validated['concept'],
            'notes'                 => $validated['notes'] ?? null,
            'counterpart_account_id'=> $validated['counterpart_account_id'],
            'journal_entry_id'      => $entry->id,
            'created_by_user_id'    => auth()->id(),
        ]);

        return redirect()->route('finances.cash-sessions.show', $cashSession)
            ->with('success', 'Movimiento registrado.');
    }

    public function destroy(CashSession $cashSession, CashMovement $cashMovement): RedirectResponse
    {
        if (! $cashSession->isOpen()) {
            return back()->with('error', 'La sesión ya está cerrada — no se puede eliminar el movimiento.');
        }

        if ($cashMovement->journalEntry) {
            $cashMovement->journalEntry->lines()->delete();
            $cashMovement->journalEntry->delete();
        }

        $cashMovement->delete();

        return redirect()->route('finances.cash-sessions.show', $cashSession)
            ->with('success', 'Movimiento eliminado.');
    }

    private function entryDescription(string $type, string $concept): string
    {
        $labels = [
            'retiro'        => 'Retiro de caja',
            'deposito_banco'=> 'Depósito a banco',
            'gasto'         => 'Gasto de caja',
            'perdida'       => 'Pérdida / faltante',
            'entrada'       => 'Entrada de efectivo',
        ];

        return ($labels[$type] ?? 'Movimiento de caja') . ' — ' . $concept;
    }
}
