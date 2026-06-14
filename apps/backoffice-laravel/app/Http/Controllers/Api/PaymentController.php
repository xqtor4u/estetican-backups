<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankLedger;
use App\Models\CashLedger;
use App\Models\SpaBooking;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** Pagos ya registrados contra esta cita */
    public function index(SpaBooking $booking)
    {
        $morph = SpaBooking::class;

        $cash = CashLedger::where('payable_type', $morph)
            ->where('payable_id', $booking->id)
            ->get(['id', 'amount', 'payment_method', 'category', 'notes', 'created_at']);

        $bank = BankLedger::where('payable_type', $morph)
            ->where('payable_id', $booking->id)
            ->get(['id', 'amount', 'payment_method', 'category', 'notes', 'created_at']);

        $all = $cash->map(fn ($r) => [...$r->toArray(), 'destination' => 'caja'])
            ->concat($bank->map(fn ($r) => [...$r->toArray(), 'destination' => 'banco']))
            ->sortBy('created_at')
            ->values();

        return response()->json([
            'payments' => $all,
            'paid'     => $all->sum('amount'),
        ]);
    }

    /**
     * Registra el cobro y marca la cita como completada.
     * Campos: amount, payment_method, destination (caja|banco), notes, mark_completed
     */
    public function store(Request $request, SpaBooking $booking)
    {
        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'No se puede cobrar una cita cancelada.'], 422);
        }

        $data = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'destination'    => 'required|in:caja,banco',
            'notes'          => 'nullable|string|max:500',
            'mark_completed' => 'boolean',
        ]);

        $morph      = SpaBooking::class;
        $clientId   = $booking->pet->client_id;
        $attributes = [
            'client_id'      => $clientId,
            'payable_type'   => $morph,
            'payable_id'     => $booking->id,
            'amount'         => $data['amount'],
            'payment_method' => $data['payment_method'],
            'category'       => 'liquidacion',
            'notes'          => $data['notes'] ?? null,
        ];

        if ($data['destination'] === 'banco') {
            BankLedger::create($attributes);
        } else {
            CashLedger::create($attributes);
        }

        // Marcar la cita como completada si se solicita
        if ($data['mark_completed'] ?? true) {
            $booking->update(['status' => 'completed']);
        }

        // Devolver el resumen actualizado
        return $this->index(new Request(), $booking->fresh());
    }
}
