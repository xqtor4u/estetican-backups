<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\BankLedger;
use App\Models\CashLedger;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SpaBooking;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(SpaBooking $booking)
    {
        $morph = SpaBooking::class;

        $payments = Payment::where('payable_type', $morph)
            ->where('payable_id', $booking->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($p) => [
                'id'             => $p->id,
                'amount'         => (float) $p->amount,
                'payment_method' => $p->payment_method,
                'category'       => $p->category,
                'destination'    => $p->destination ?? 'caja',
                'notes'          => $p->notes,
                'created_at'     => $p->created_at,
            ]);

        // Registros legacy de libros auxiliares (backward compat hasta BL-021)
        $cash = CashLedger::where('payable_type', $morph)
            ->where('payable_id', $booking->id)
            ->get(['id', 'amount', 'payment_method', 'category', 'notes', 'created_at'])
            ->map(fn ($r) => [...$r->toArray(), 'destination' => 'caja', 'amount' => (float) $r->amount]);

        $bank = BankLedger::where('payable_type', $morph)
            ->where('payable_id', $booking->id)
            ->get(['id', 'amount', 'payment_method', 'category', 'notes', 'created_at'])
            ->map(fn ($r) => [...$r->toArray(), 'destination' => 'banco', 'amount' => (float) $r->amount]);

        $all = $payments->concat($cash)->concat($bank)
            ->sortBy('created_at')
            ->values();

        return response()->json([
            'payments' => $all,
            'paid'     => round($all->sum('amount'), 2),
        ]);
    }

    public function store(Request $request, SpaBooking $booking)
    {
        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'No se puede cobrar una cita cancelada.'], 422);
        }

        $data = $request->validate([
            'amount'              => 'required|numeric|min:0.01',
            'payment_method_code' => 'nullable|string|exists:payment_methods,code',
            'payment_method'      => 'required_without:payment_method_code|string|max:50',
            'destination'         => 'required_without:payment_method_code|in:caja,banco',
            'reference'           => 'nullable|string|max:255',
            'notes'               => 'nullable|string|max:500',
            'mark_completed'      => 'boolean',
        ]);

        if (! empty($data['payment_method_code'])) {
            $paymentMethod = PaymentMethod::where('code', $data['payment_method_code'])->first();
            $methodName    = $paymentMethod->name;
            $destination   = $paymentMethod->type === 'cash' ? 'caja' : 'banco';
        } else {
            $paymentMethod = null;
            $methodName    = $data['payment_method'];
            $destination   = $data['destination'];
        }

        Payment::create([
            'client_id'          => $booking->pet->client_id,
            'payable_type'       => SpaBooking::class,
            'payable_id'         => $booking->id,
            'amount'             => $data['amount'],
            'payment_method'     => $methodName,
            'destination'        => $destination,
            'external_reference' => $data['reference'] ?? null,
            'category'           => 'liquidacion',
            'notes'              => $data['notes'] ?? null,
        ]);

        if ($paymentMethod?->account_id) {
            try {
                app(AccountingServiceInterface::class)->createEntryForBookingPayment(
                    $booking,
                    $paymentMethod,
                    (float) $data['amount'],
                    $data['reference'] ?? null,
                    $data['notes'] ?? null
                );
            } catch (\Throwable) {
                // No interrumpir el cobro si el asiento contable falla
            }
        }

        if ($data['mark_completed'] ?? true) {
            $booking->update(['status' => 'completed']);
        }

        return $this->index($booking->fresh());
    }
}
