<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Contracts\CashSessionExpectedAmountServiceInterface;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashSessionExpectedAmountService implements CashSessionExpectedAmountServiceInterface
{
    public function periodStart(CashSession $cashSession): ?Carbon
    {
        $prev = CashSession::where('cash_register_id', $cashSession->cash_register_id)
            ->where('id', '<', $cashSession->id)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->value('closed_at');

        return $prev ? Carbon::parse($prev) : null;
    }

    public function paymentsForPeriod(?Carbon $from, mixed $until): Collection
    {
        $applyRange = fn ($q, string $col) => $q
            ->when($from,  fn ($q) => $q->where($col, '>=', $from))
            ->when($until, fn ($q) => $q->where($col, '<=', $until));

        // payments (nuevo)
        $newPayments = Payment::with('client')
            ->when($from,  fn ($q) => $q->where('created_at', '>=', $from))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->get()
            ->map(fn ($p) => (object) [
                'id'             => 'p-'.$p->id,
                'created_at'     => $p->created_at,
                'client_name'    => $p->client?->full_name,
                'destination'    => $p->destination,
                'payment_method' => $p->payment_method,
                'amount'         => (float) $p->amount,
            ]);

        // cash_ledgers (legacy)
        $cashRows = $applyRange(DB::table('cash_ledgers'), 'cash_ledgers.created_at')
            ->leftJoin('clients', 'cash_ledgers.client_id', '=', 'clients.id')
            ->select('cash_ledgers.id', 'cash_ledgers.created_at', DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"),
                     'cash_ledgers.amount', 'cash_ledgers.payment_method')
            ->get()
            ->map(fn ($r) => (object) [
                'id'             => 'cl-'.$r->id,
                'created_at'     => $r->created_at,
                'client_name'    => $r->client_name,
                'destination'    => 'caja',
                'payment_method' => $r->payment_method,
                'amount'         => (float) $r->amount,
            ]);

        // bank_ledgers (legacy)
        $bankRows = $applyRange(DB::table('bank_ledgers'), 'bank_ledgers.created_at')
            ->leftJoin('clients', 'bank_ledgers.client_id', '=', 'clients.id')
            ->select('bank_ledgers.id', 'bank_ledgers.created_at', 'bank_ledgers.cleared_at',
                     DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"),
                     'bank_ledgers.amount', 'bank_ledgers.payment_method')
            ->get()
            ->map(fn ($r) => (object) [
                'id'             => 'bl-'.$r->id,
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

    public function expectedAmount(CashSession $cashSession, mixed $until = null): float
    {
        $from = $this->periodStart($cashSession);
        $totalEfectivo = $this->paymentsForPeriod($from, $until)
            ->where('destination', 'caja')
            ->sum('amount');

        $movements = CashMovement::where('cash_session_id', $cashSession->id)->get();
        $totalEntradas = $movements->where('direction', 'entrada')->sum('amount');
        $totalSalidas  = $movements->where('direction', 'salida')->sum('amount');

        return round($cashSession->opening_amount + $totalEfectivo + $totalEntradas - $totalSalidas, 2);
    }
}
