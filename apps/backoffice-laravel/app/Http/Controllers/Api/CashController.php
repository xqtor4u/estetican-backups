<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\OperatorCheckin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashController extends Controller
{
    private const CAJA_ACCOUNT_ID = 6;
    private const SALIDA_TYPES = ['retiro', 'deposito_banco', 'gasto', 'perdida'];

    public function session(): JsonResponse
    {
        $user    = auth()->user();
        $checkin = OperatorCheckin::where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->with('branch:id,name')
            ->latest('checked_in_at')
            ->first();

        if (! $checkin) {
            return response()->json(['status' => 'no_checkin']);
        }

        $cashSession = CashSession::where('branch_id', $checkin->branch_id)
            ->where('status', 'abierta')
            ->with(['cashRegister:id,name', 'branch:id,name', 'openedBy:id,name'])
            ->first();

        if (! $cashSession) {
            return response()->json([
                'status' => 'no_session',
                'branch' => ['id' => $checkin->branch_id, 'name' => $checkin->branch->name],
            ]);
        }

        $movements = CashMovement::where('cash_session_id', $cashSession->id)
            ->with('counterpartAccount:id,name')
            ->orderByDesc('created_at')
            ->get();

        $totalEntradas = $movements->where('direction', 'entrada')->sum('amount');
        $totalSalidas  = $movements->where('direction', 'salida')->sum('amount');

        return response()->json([
            'status'  => 'active',
            'session' => [
                'id'             => $cashSession->id,
                'register_name'  => $cashSession->cashRegister->name,
                'branch_name'    => $cashSession->branch->name,
                'opened_by'      => $cashSession->openedBy?->name,
                'opened_at'      => $cashSession->opened_at,
                'opening_amount' => $cashSession->opening_amount,
                'notes'          => $cashSession->notes,
            ],
            'totals' => [
                'opening_amount' => $cashSession->opening_amount,
                'total_entradas' => round($totalEntradas, 2),
                'total_salidas'  => round($totalSalidas, 2),
                'saldo_esperado' => round($cashSession->opening_amount + $totalEntradas - $totalSalidas, 2),
            ],
            'movements' => $movements->map(fn ($m) => [
                'id'         => $m->id,
                'type'       => $m->type,
                'direction'  => $m->direction,
                'amount'     => $m->amount,
                'concept'    => $m->concept,
                'notes'      => $m->notes,
                'account'    => $m->counterpartAccount?->name,
                'created_at' => $m->created_at,
            ])->values(),
        ]);
    }

    public function movementTypes(): JsonResponse
    {
        $gastoAccounts = Account::where('type', 'gasto')
            ->whereNotIn('id', [5])
            ->orderBy('code')
            ->get(['id', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])
            ->values();

        $bankAccounts = Account::whereBetween('id', [7, 8])
            ->orderBy('code')
            ->get(['id', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])
            ->values();

        $capAccounts = Account::where('type', 'capital')
            ->whereNotIn('id', [3])
            ->orderBy('code')
            ->get(['id', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])
            ->values();

        return response()->json([
            [
                'type'      => 'retiro',
                'label'     => 'Retiro / disposición',
                'direction' => 'salida',
                'icon'      => 'account_balance_wallet',
                'accounts'  => $gastoAccounts,
            ],
            [
                'type'      => 'deposito_banco',
                'label'     => 'Depósito a banco',
                'direction' => 'salida',
                'icon'      => 'account_balance',
                'accounts'  => $bankAccounts,
            ],
            [
                'type'      => 'gasto',
                'label'     => 'Gasto de caja',
                'direction' => 'salida',
                'icon'      => 'receipt_long',
                'accounts'  => $gastoAccounts,
            ],
            [
                'type'      => 'perdida',
                'label'     => 'Pérdida / faltante',
                'direction' => 'salida',
                'icon'      => 'money_off',
                'accounts'  => [['id' => 24, 'name' => 'Gastos generales']],
            ],
            [
                'type'      => 'entrada',
                'label'     => 'Entrada de efectivo',
                'direction' => 'entrada',
                'icon'      => 'add_circle',
                'accounts'  => $capAccounts,
            ],
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $checkin = OperatorCheckin::where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if (! $checkin) {
            return response()->json(['error' => 'Sin check-in activo'], 403);
        }

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'type'      => ['nullable', 'string'],
            'direction' => ['nullable', 'in:entrada,salida'],
        ]);

        $from  = $request->filled('date_from') ? $request->date_from . ' 00:00:00' : null;
        $until = $request->filled('date_to')   ? $request->date_to   . ' 23:59:59' : null;

        $typeFilter = $request->input('type');
        $cobroTypes = ['cobro_efectivo', 'cobro_banco'];
        $movTypes   = ['retiro', 'deposito_banco', 'gasto', 'perdida', 'entrada'];

        $items = collect();

        // ── Movimientos manuales de caja ──────────────────────
        $includeMovements = ! $typeFilter || in_array($typeFilter, $movTypes);
        if ($includeMovements) {
            $q = CashMovement::query()
                ->whereHas('cashSession', fn ($q) => $q->where('branch_id', $checkin->branch_id))
                ->with('counterpartAccount:id,name')
                ->when($from,  fn ($q) => $q->where('created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
                ->when($typeFilter && in_array($typeFilter, $movTypes), fn ($q) => $q->where('type', $typeFilter));

            $items = $items->concat($q->get()->map(fn ($m) => [
                'id'          => 'm-' . $m->id,
                'type'        => $m->type,
                'direction'   => $m->direction,
                'amount'      => (float) $m->amount,
                'concept'     => $m->concept,
                'notes'       => $m->notes,
                'account'     => $m->counterpartAccount?->name,
                'client_name' => null,
                'created_at'  => $m->created_at->toISOString(),
            ]));
        }

        // ── Cobros a clientes (payments — nuevo sistema) ──────
        $includeEfectivo = ! $typeFilter || $typeFilter === 'cobro_efectivo';
        $includeBanco    = ! $typeFilter || $typeFilter === 'cobro_banco';

        if ($includeEfectivo || $includeBanco) {
            $payments = \App\Models\Payment::with('client')
                ->when($from,  fn ($q) => $q->where('created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
                ->when($includeEfectivo && ! $includeBanco, fn ($q) => $q->where('destination', 'caja'))
                ->when($includeBanco    && ! $includeEfectivo, fn ($q) => $q->where('destination', 'banco'))
                ->get();

            $items = $items->concat($payments->map(fn ($p) => [
                'id'          => 'p-' . $p->id,
                'type'        => $p->destination === 'caja' ? 'cobro_efectivo' : 'cobro_banco',
                'direction'   => 'entrada',
                'amount'      => (float) $p->amount,
                'concept'     => 'Cobro de servicio',
                'notes'       => $p->notes,
                'account'     => $p->payment_method,
                'client_name' => $p->client?->full_name,
                'created_at'  => \Illuminate\Support\Carbon::parse($p->created_at)->toISOString(),
            ]));
        }

        // ── Cobros legacy (cash_ledgers) ──────────────────────
        if ($includeEfectivo) {
            $cashRows = \Illuminate\Support\Facades\DB::table('cash_ledgers')
                ->leftJoin('clients', 'cash_ledgers.client_id', '=', 'clients.id')
                ->when($from,  fn ($q) => $q->where('cash_ledgers.created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('cash_ledgers.created_at', '<=', $until))
                ->select('cash_ledgers.id', 'cash_ledgers.created_at', 'cash_ledgers.amount',
                         'cash_ledgers.payment_method',
                         \Illuminate\Support\Facades\DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"))
                ->get();

            $items = $items->concat($cashRows->map(fn ($r) => [
                'id'          => 'cl-' . $r->id,
                'type'        => 'cobro_efectivo',
                'direction'   => 'entrada',
                'amount'      => (float) $r->amount,
                'concept'     => 'Cobro de servicio',
                'notes'       => null,
                'account'     => $r->payment_method,
                'client_name' => trim($r->client_name) ?: null,
                'created_at'  => \Illuminate\Support\Carbon::parse($r->created_at)->toISOString(),
            ]));
        }

        // ── Cobros legacy (bank_ledgers) ──────────────────────
        if ($includeBanco) {
            $bankRows = \Illuminate\Support\Facades\DB::table('bank_ledgers')
                ->leftJoin('clients', 'bank_ledgers.client_id', '=', 'clients.id')
                ->when($from,  fn ($q) => $q->where('bank_ledgers.created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('bank_ledgers.created_at', '<=', $until))
                ->select('bank_ledgers.id', 'bank_ledgers.created_at', 'bank_ledgers.amount',
                         'bank_ledgers.payment_method',
                         \Illuminate\Support\Facades\DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"))
                ->get();

            $items = $items->concat($bankRows->map(fn ($r) => [
                'id'          => 'bl-' . $r->id,
                'type'        => 'cobro_banco',
                'direction'   => 'entrada',
                'amount'      => (float) $r->amount,
                'concept'     => 'Cobro de servicio',
                'notes'       => null,
                'account'     => $r->payment_method,
                'client_name' => trim($r->client_name) ?: null,
                'created_at'  => \Illuminate\Support\Carbon::parse($r->created_at)->toISOString(),
            ]));
        }

        $sorted = $items->sortByDesc('created_at')->values();

        return response()->json([
            'movements' => $sorted,
            'totals'    => [
                'total_entradas' => round($sorted->where('direction', 'entrada')->sum('amount'), 2),
                'total_salidas'  => round($sorted->where('direction', 'salida')->sum('amount'), 2),
                'count'          => $sorted->count(),
            ],
        ]);
    }

    public function storeMovement(Request $request, CashSession $cashSession): JsonResponse
    {
        if (! $cashSession->isOpen()) {
            return response()->json(['message' => 'La sesión ya está cerrada.'], 422);
        }

        $validated = $request->validate([
            'type'                   => ['required', 'in:retiro,deposito_banco,gasto,perdida,entrada'],
            'amount'                 => ['required', 'numeric', 'min:0.01'],
            'concept'                => ['required', 'string', 'max:255'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
            'counterpart_account_id' => ['required', 'exists:accounts,id'],
        ]);

        $direction = in_array($validated['type'], self::SALIDA_TYPES) ? 'salida' : 'entrada';
        $amount    = (float) $validated['amount'];

        $labels = [
            'retiro'         => 'Retiro de caja',
            'deposito_banco' => 'Depósito a banco',
            'gasto'          => 'Gasto de caja',
            'perdida'        => 'Pérdida / faltante',
            'entrada'        => 'Entrada de efectivo',
        ];

        $entry = JournalEntry::create([
            'entry_date'         => now()->toDateString(),
            'description'        => ($labels[$validated['type']] ?? 'Movimiento de caja') . ' — ' . $validated['concept'],
            'status'             => 'aplicado',
            'branch_id'          => $cashSession->branch_id,
            'created_by_user_id' => auth()->id(),
            'posted_by_user_id'  => auth()->id(),
            'posted_at'          => now(),
            'reference_type'     => CashSession::class,
            'reference_id'       => $cashSession->id,
        ]);

        if ($direction === 'salida') {
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $validated['counterpart_account_id'], 'debit' => $amount, 'credit' => 0, 'description' => $validated['concept']]);
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => self::CAJA_ACCOUNT_ID, 'debit' => 0, 'credit' => $amount, 'description' => $validated['concept']]);
        } else {
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => self::CAJA_ACCOUNT_ID, 'debit' => $amount, 'credit' => 0, 'description' => $validated['concept']]);
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $validated['counterpart_account_id'], 'debit' => 0, 'credit' => $amount, 'description' => $validated['concept']]);
        }

        $movement = CashMovement::create([
            'cash_session_id'        => $cashSession->id,
            'type'                   => $validated['type'],
            'direction'              => $direction,
            'amount'                 => $amount,
            'concept'                => $validated['concept'],
            'notes'                  => $validated['notes'] ?? null,
            'counterpart_account_id' => $validated['counterpart_account_id'],
            'journal_entry_id'       => $entry->id,
            'created_by_user_id'     => auth()->id(),
        ]);

        $movement->load('counterpartAccount:id,name');

        return response()->json([
            'id'         => $movement->id,
            'type'       => $movement->type,
            'direction'  => $movement->direction,
            'amount'     => $movement->amount,
            'concept'    => $movement->concept,
            'notes'      => $movement->notes,
            'account'    => $movement->counterpartAccount?->name,
            'created_at' => $movement->created_at,
        ], 201);
    }
}
