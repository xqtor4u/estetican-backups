<?php

namespace App\Http\Controllers\Finances;

use App\Domain\Accounting\Contracts\CashSessionExpectedAmountServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CashSessionController extends Controller
{
    public function __construct(
        private readonly CashSessionExpectedAmountServiceInterface $expectedAmountService,
    ) {}

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
        $validated = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        // Bloqueo a nivel de fila (mismo patrón que AccountingService::getNextFolio()) para
        // que dos requests casi simultáneas sobre la misma caja (doble clic, dos operadores)
        // no pasen el chequeo "¿hay sesión abierta?" antes de que exista la primera — sin esto,
        // se podían crear dos CashSession con status='abierta' para la misma caja, y
        // CashSessionExpectedAmountService::periodStart()/paymentsForPeriod() asumen como
        // máximo una.
        $session = DB::transaction(function () use ($request, $cashRegister, $validated) {
            $lockedRegister = CashRegister::lockForUpdate()->findOrFail($cashRegister->id);

            if ($lockedRegister->activeSession) {
                return null;
            }

            return CashSession::create([
                'cash_register_id'   => $lockedRegister->id,
                'branch_id'          => $lockedRegister->branch_id,
                'opened_by_user_id'  => auth()->id(),
                'opened_at'          => now(),
                'opening_amount'     => $validated['opening_amount'],
                'notes'              => $validated['notes'] ?? null,
                'status'             => 'abierta',
            ]);
        });

        if ($session === null) {
            return redirect()->route('finances.cash-sessions.show', $cashRegister->activeSession()->firstOrFail())
                ->with('info', 'La caja ya tiene una sesión abierta.');
        }

        return redirect()->route('finances.cash-sessions.show', $session)
            ->with('success', 'Sesión abierta.');
    }

    public function show(CashSession $cashSession): View
    {
        $cashSession->load(['cashRegister', 'branch', 'openedBy', 'closedBy']);

        $from = $this->expectedAmountService->periodStart($cashSession);
        $until = $cashSession->closed_at;

        $payments = $this->expectedAmountService->paymentsForPeriod($from, $until);

        $movements = CashMovement::where('cash_session_id', $cashSession->id)
            ->with('counterpartAccount', 'createdBy')
            ->orderBy('created_at')
            ->get();

        $totalEfectivo  = $payments->where('destination', 'caja')->sum('amount');
        $totalBanco     = $payments->where('destination', 'banco')->sum('amount');
        $totalEntradas  = $movements->where('direction', 'entrada')->sum('amount');
        $totalSalidas   = $movements->where('direction', 'salida')->sum('amount');
        $totalCobros    = $totalEfectivo;
        $expectedAmount = $this->expectedAmountService->expectedAmount($cashSession, $until);

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

        // Hallazgo real (16/08/2026): esta pantalla de previsualización (antes de cerrar)
        // calculaba "esperado" como solo fondo inicial + cobros, sin sumar/restar los
        // movimientos manuales (CashMovement) — una TERCERA fórmula distinta de la que
        // realmente usaba doClose() para cerrar de verdad. El número que se le mostraba al
        // operador antes de contar la caja no coincidía con el que se usaba para calcular la
        // diferencia real un instante después. Corregido para usar la misma fuente única.
        $from           = $this->expectedAmountService->periodStart($cashSession);
        $totalCobros    = $this->expectedAmountService->paymentsForPeriod($from, null)
            ->where('destination', 'caja')
            ->sum('amount');
        $expectedAmount = $this->expectedAmountService->expectedAmount($cashSession);

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

        $expected   = $this->expectedAmountService->expectedAmount($cashSession);
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
}
