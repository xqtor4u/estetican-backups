<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Contracts\CashSessionExpectedAmountServiceInterface;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        // SYNC-098: `payments` es la tabla única canónica de cobro — antes esto mezclaba
        // además cash_ledgers/bank_ledgers del camino web de anticipos/liquidación.
        return Payment::with('client')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
            ->get()
            ->map(fn ($p) => (object) [
                'id' => 'p-'.$p->id,
                'created_at' => $p->created_at,
                'client_name' => $p->client?->full_name,
                'destination' => $p->destination,
                'payment_method' => $p->payment_method,
                'amount' => (float) $p->amount,
            ])
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
        $totalSalidas = $movements->where('direction', 'salida')->sum('amount');

        return round($cashSession->opening_amount + $totalEfectivo + $totalEntradas - $totalSalidas, 2);
    }
}
