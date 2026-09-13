<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Domain\Inventory\Contracts\BookingStockConsumptionServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SpaBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentController extends Controller
{
    /** Mismo criterio que `BookingController::ensureVisible()` — 404, no confirmar existencia. */
    private function ensureVisible(SpaBooking $booking): void
    {
        abort_unless(SpaBooking::visibleTo(auth()->user())->whereKey($booking->id)->exists(), 404);
    }

    public function index(SpaBooking $booking)
    {
        $this->ensureVisible($booking);

        // SYNC-098: `payments` es la tabla única canónica de cobro (móvil Y web) — antes este
        // endpoint mezclaba además cash_ledgers/bank_ledgers del camino web.
        $payments = Payment::where('payable_type', SpaBooking::class)
            ->where('payable_id', $booking->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method,
                'category' => $p->category,
                'destination' => $p->destination ?? 'caja',
                'notes' => $p->notes,
                'created_at' => $p->created_at,
            ])
            ->values();

        return response()->json([
            'payments' => $payments,
            'paid' => round($payments->sum('amount'), 2),
        ]);
    }

    public function store(Request $request, SpaBooking $booking)
    {
        $this->ensureVisible($booking);

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'No se puede cobrar una cita cancelada.'], 422);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method_code' => 'nullable|string|exists:payment_methods,code',
            'payment_method' => 'required_without:payment_method_code|string|max:50',
            'destination' => 'required_without:payment_method_code|in:caja,banco',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'mark_completed' => 'boolean',
        ]);

        if (! empty($data['payment_method_code'])) {
            $paymentMethod = PaymentMethod::where('code', $data['payment_method_code'])->first();
            $methodName = $paymentMethod->name;
            $destination = $paymentMethod->type === 'cash' ? 'caja' : 'banco';
        } else {
            $paymentMethod = null;
            $methodName = $data['payment_method'];
            $destination = $data['destination'];
        }

        $paymentAttributes = [
            'client_id' => $booking->pet->client_id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => $data['amount'],
            'payment_method' => $methodName,
            'destination' => $destination,
            'external_reference' => $data['reference'] ?? null,
            'category' => 'liquidacion',
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $request->user()->id,
        ];

        if ($paymentMethod) {
            // Con método de pago identificado (payment_method_code), el recibo/asiento contable
            // es obligatorio y transaccional (BL-076) — ya no se silencia si falla.
            try {
                DB::transaction(function () use ($booking, $paymentAttributes, $paymentMethod, $data) {
                    $payment = Payment::create($paymentAttributes);

                    app(AccountingServiceInterface::class)->recordBookingPayment(
                        $booking,
                        $payment,
                        $paymentMethod,
                        (float) $data['amount'],
                        $data['reference'] ?? null,
                        $data['notes'] ?? null
                    );
                });
            } catch (RuntimeException $e) {
                Log::warning('No se pudo generar el recibo/asiento contable de un cobro móvil — falta configuración', [
                    'booking_id' => $booking->id,
                    'payment_method_id' => $paymentMethod->id,
                    'exception' => $e->getMessage(),
                ]);

                return response()->json(['message' => $e->getMessage()], 422);
            }
        } else {
            // Payload legacy sin payment_method_code (payment_method/destination sueltos):
            // sin PaymentMethod no hay cuenta contable a la que ligar el recibo — se registra
            // el cobro igual, pero sin Document/JournalEntry, como ya funcionaba antes de BL-076.
            Payment::create($paymentAttributes);
        }

        if ($data['mark_completed'] ?? true) {
            $booking->update(['status' => 'completed']);
            app(BookingStockConsumptionServiceInterface::class)->consume($booking, auth()->id());
        }

        return $this->index($booking->fresh());
    }
}
