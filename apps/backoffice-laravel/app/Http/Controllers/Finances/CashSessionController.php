<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CashSessionController extends Controller
{
    public function index(): View
    {
        $sessions = CashSession::with(['cashRegister', 'branch', 'openedBy', 'closedBy'])
            ->orderByDesc('opened_at')
            ->paginate(30);

        return view('finances.cash-sessions.index', compact('sessions'));
    }

    public function open(CashRegister $cashRegister): View|RedirectResponse
    {
        if ($cashRegister->activeSession) {
            return redirect()->route('finances.cash-sessions.show', $cashRegister->activeSession)
                ->with('info', 'La caja ya tiene una sesión abierta.');
        }

        return view('finances.cash-sessions.open', compact('cashRegister'));
    }

    public function store(Request $request, CashRegister $cashRegister): RedirectResponse
    {
        if ($cashRegister->activeSession) {
            return redirect()->route('finances.cash-sessions.show', $cashRegister->activeSession)
                ->with('info', 'La caja ya tiene una sesión abierta.');
        }

        $validated = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $session = CashSession::create([
            'cash_register_id'   => $cashRegister->id,
            'branch_id'          => $cashRegister->branch_id,
            'opened_by_user_id'  => auth()->id(),
            'opened_at'          => now(),
            'opening_amount'     => $validated['opening_amount'],
            'notes'              => $validated['notes'] ?? null,
            'status'             => 'abierta',
        ]);

        return redirect()->route('finances.cash-sessions.show', $session)
            ->with('success', 'Sesión abierta.');
    }

    public function show(CashSession $cashSession): View
    {
        $cashSession->load(['cashRegister', 'branch', 'openedBy', 'closedBy']);

        $from = $this->periodStart($cashSession);
        $until = $cashSession->closed_at;

        $payments = $this->allPaymentsForPeriod($from, $until);

        $movements = CashMovement::where('cash_session_id', $cashSession->id)
            ->with('counterpartAccount', 'createdBy')
            ->orderBy('created_at')
            ->get();

        $totalEfectivo  = $payments->where('destination', 'caja')->sum('amount');
        $totalBanco     = $payments->where('destination', 'banco')->sum('amount');
        $totalEntradas  = $movements->where('direction', 'entrada')->sum('amount');
        $totalSalidas   = $movements->where('direction', 'salida')->sum('amount');
        $totalCobros    = $totalEfectivo;
        $expectedAmount = $cashSession->opening_amount + $totalEfectivo + $totalEntradas - $totalSalidas;

        $movementTypeOptions = [
            'retiro'        => 'Retiro / disposición',
            'deposito_banco'=> 'Depósito a banco',
            'gasto'         => 'Gasto de caja',
            'perdida'       => 'Pérdida / faltante',
            'entrada'       => 'Entrada de efectivo',
        ];
        $gatoAccounts  = Account::where('type', 'gasto')->whereNotIn('id', [5])->orderBy('code')->pluck('name', 'id');
        $bankAccounts  = Account::whereBetween('id', [7, 8])->orderBy('code')->pluck('name', 'id');
        $capAccounts   = Account::where('type', 'capital')->whereNotIn('id', [3])->orderBy('code')->pluck('name', 'id');

        return view('finances.cash-sessions.show', compact(
            'cashSession', 'payments', 'movements',
            'totalEfectivo', 'totalBanco', 'totalEntradas', 'totalSalidas', 'totalCobros', 'expectedAmount',
            'movementTypeOptions', 'gatoAccounts', 'bankAccounts', 'capAccounts'
        ));
    }

    public function close(CashSession $cashSession): View|RedirectResponse
    {
        if (! $cashSession->isOpen()) {
            return redirect()->route('finances.cash-sessions.show', $cashSession)
                ->with('info', 'Esta sesión ya está cerrada.');
        }

        $cashSession->load(['cashRegister', 'branch', 'openedBy']);

        $from = $this->periodStart($cashSession);

        $totalCobros    = $this->allPaymentsForPeriod($from, null)->where('destination', 'caja')->sum('amount');
        $expectedAmount = $cashSession->opening_amount + $totalCobros;

        return view('finances.cash-sessions.close', compact('cashSession', 'totalCobros', 'expectedAmount'));
    }

    public function doClose(Request $request, CashSession $cashSession): RedirectResponse
    {
        if (! $cashSession->isOpen()) {
            return redirect()->route('finances.cash-sessions.show', $cashSession)
                ->with('info', 'Esta sesión ya está cerrada.');
        }

        $validated = $request->validate([
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $from = $this->periodStart($cashSession);

        $totalCobros  = $this->allPaymentsForPeriod($from, $cashSession->closed_at)
            ->where('destination', 'caja')->sum('amount');
        $movements    = CashMovement::where('cash_session_id', $cashSession->id)->get();
        $totalEntradas = $movements->where('direction', 'entrada')->sum('amount');
        $totalSalidas  = $movements->where('direction', 'salida')->sum('amount');

        $expected   = round($cashSession->opening_amount + $totalCobros + $totalEntradas - $totalSalidas, 2);
        $closing    = round((float) $validated['closing_amount'], 2);
        $difference = round($closing - $expected, 2);

        $cashSession->update([
            'closed_by_user_id' => auth()->id(),
            'closed_at'         => now(),
            'closing_amount'    => $closing,
            'expected_amount'   => $expected,
            'difference'        => $difference,
            'notes'             => $validated['notes'] ?? $cashSession->notes,
            'status'            => 'cerrada',
        ]);

        return redirect()->route('finances.cash-sessions.show', $cashSession)
            ->with('success', 'Sesión cerrada. Diferencia: $' . number_format(abs($difference), 2) . ($difference >= 0 ? ' sobrante' : ' faltante'));
    }

    /**
     * Devuelve el inicio del período de esta sesión.
     * Si hay una sesión anterior cerrada en la misma caja, el período empieza cuando esa cerró.
     * Si es la primera sesión, no hay límite inferior (muestra todos los cobros históricos).
     */
    private function periodStart(CashSession $cashSession): ?\Illuminate\Support\Carbon
    {
        $prev = CashSession::where('cash_register_id', $cashSession->cash_register_id)
            ->where('id', '<', $cashSession->id)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->value('closed_at');

        return $prev ? \Illuminate\Support\Carbon::parse($prev) : null;
    }

    /**
     * Devuelve una colección unificada de cobros del período,
     * combinando payments (sistema nuevo), cash_ledgers y bank_ledgers (sistema legacy).
     */
    private function allPaymentsForPeriod(
        ?\Illuminate\Support\Carbon $from,
        mixed $until
    ): \Illuminate\Support\Collection {
        $applyRange = fn ($q, string $col) => $q
            ->when($from,  fn ($q) => $q->where($col, '>=', $from))
            ->when($until, fn ($q) => $q->where($col, '<=', $until));

        // payments (nuevo)
        $newPayments = Payment::with('client')
            ->when($from,  fn ($q) => $q->where('created_at', '>=', $from))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->get()
            ->map(fn ($p) => (object) [
                'created_at'     => $p->created_at,
                'client_name'    => $p->client?->full_name,
                'destination'    => $p->destination,
                'payment_method' => $p->payment_method,
                'amount'         => (float) $p->amount,
            ]);

        // cash_ledgers (legacy)
        $cashRows = $applyRange(DB::table('cash_ledgers'), 'cash_ledgers.created_at')
            ->leftJoin('clients', 'cash_ledgers.client_id', '=', 'clients.id')
            ->select('cash_ledgers.created_at', DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"),
                     'cash_ledgers.amount', 'cash_ledgers.payment_method')
            ->get()
            ->map(fn ($r) => (object) [
                'created_at'     => $r->created_at,
                'client_name'    => $r->client_name,
                'destination'    => 'caja',
                'payment_method' => $r->payment_method,
                'amount'         => (float) $r->amount,
            ]);

        // bank_ledgers (legacy)
        $bankRows = $applyRange(DB::table('bank_ledgers'), 'bank_ledgers.created_at')
            ->leftJoin('clients', 'bank_ledgers.client_id', '=', 'clients.id')
            ->select('bank_ledgers.created_at', 'bank_ledgers.cleared_at',
                     DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"),
                     'bank_ledgers.amount', 'bank_ledgers.payment_method')
            ->get()
            ->map(fn ($r) => (object) [
                'created_at'     => $r->created_at,
                'client_name'    => $r->client_name,
                'destination'    => 'banco',
                'payment_method' => $r->payment_method,
                'amount'         => (float) $r->amount,
                'cleared_at'     => $r->cleared_at,
            ]);

        return collect()
            ->merge($newPayments)
            ->merge($cashRows)
            ->merge($bankRows)
            ->sortByDesc('created_at')
            ->values();
    }
}
