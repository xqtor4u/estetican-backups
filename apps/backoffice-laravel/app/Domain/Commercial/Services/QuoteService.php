<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Domain\Commercial\Contracts\QuoteServiceInterface;
use App\Models\BankLedger;
use App\Models\CashLedger;
use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\SpaBooking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuoteService implements QuoteServiceInterface
{
    public function __construct(
        private AccountingServiceInterface $accountingService
    ) {}

    /**
     * Create a new quote option for a booking.
     */
    public function createQuoteFromBooking(SpaBooking $booking, array $data): Quote
    {
        return DB::transaction(function () use ($booking, $data) {
            $quote = new Quote([
                'spa_booking_id' => $booking->id,
                'version_label' => $data['version_label'] ?? 'Propuesta',
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            $quote->save();

            $total = 0;
            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $quantity = (float) ($item['quantity'] ?? 1);
                    $unitPrice = $item['price'] ?? (
                        ! empty($item['item_id'])
                            ? Item::find($item['item_id'])->price
                            : Service::find($item['service_id'])->price
                    );

                    $quoteItem = new QuoteItem([
                        'quote_id' => $quote->id,
                        'service_id' => $item['service_id'] ?? null,
                        'item_id' => $item['item_id'] ?? null,
                        'group_id' => $item['group_id'] ?? null,
                        'quantity' => $quantity,
                        'price_override' => $item['price'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                    $quoteItem->save();

                    $total += $quantity * (float) $unitPrice;
                }
            }

            $quote->update(['total_amount' => $total]);

            return $quote->load('items.service', 'items.item');
        });
    }

    /**
     * Mark a quote as accepted and trigger the work order process.
     */
    public function acceptQuote(Quote $quote, array $acceptanceData): Quote
    {
        return DB::transaction(function () use ($quote, $acceptanceData) {
            $advancePaymentMethod = ! empty($acceptanceData['advance_payment_method_code'])
                ? PaymentMethod::where('code', $acceptanceData['advance_payment_method_code'])->first()
                : null;

            // 1. Mark this quote as accepted
            $quote->update([
                'status' => 'accepted',
                'advance_amount' => $acceptanceData['advance_amount'] ?? 0,
                'advance_payment_method' => $advancePaymentMethod?->name,
            ]);

            // 2. Reject other quotes for the same booking
            Quote::query()
                ->where('spa_booking_id', $quote->spa_booking_id)
                ->where('id', '!=', $quote->id)
                ->update(['status' => 'rejected']);

            // 3. Transform Booking status to Work Order + sync services/items from accepted
            // quote — antes de registrar el anticipo, para que el snapshot de línea del
            // recibo (BL-076) refleje los servicios recién aceptados, no una cita vacía.
            $quote->loadMissing('items.service', 'items.item');
            $booking = $quote->spaBooking;
            $booking->services()->delete();
            $booking->items()->delete();
            foreach ($quote->items as $item) {
                $lineTotal = $item->lineTotal();

                if ($item->item_id) {
                    $booking->items()->create([
                        'item_id' => $item->item_id,
                        'group_id' => $item->group_id,
                        'quantity' => $item->quantity,
                        'current_price' => $lineTotal,
                    ]);
                } else {
                    $booking->services()->create([
                        'service_id' => $item->service_id,
                        'group_id' => $item->group_id,
                        'quantity' => $item->quantity,
                        'current_price' => $lineTotal,
                    ]);
                }
            }
            $booking->update([
                'status' => 'work_order',
                'total_estimated_price' => $quote->total_amount,
            ]);

            // 4. Register the advance payment if present
            if (($acceptanceData['advance_amount'] ?? 0) > 0) {
                if (! $advancePaymentMethod) {
                    throw new RuntimeException('Selecciona un método de pago válido para registrar el anticipo.');
                }

                $this->registerPayment($booking->pet->client_id, $acceptanceData['advance_amount'], [
                    'payable_type' => Quote::class,
                    'payable_id' => $quote->id,
                    'payment_method_code' => $advancePaymentMethod->code,
                    'category' => 'advance',
                    'notes' => 'Anticipo registrado al aceptar presupuesto.',
                    'booking' => $booking,
                ]);
            }

            return $quote;
        });
    }

    /**
     * Mark a quote as rejected.
     */
    public function rejectQuote(Quote $quote, ?string $reason = null): Quote
    {
        $quote->update([
            'status' => 'rejected',
            'notes' => $quote->notes.($reason ? "\nRechazo: $reason" : ''),
        ]);

        return $quote;
    }

    /**
     * Registra un pago (anticipo o liquidación) ligado a un quote/cita — BL-076: genera
     * también el recibo real (Document + JournalEntry), de forma obligatoria y transaccional
     * (antes esto solo escribía CashLedger/BankLedger, sin ningún recibo real).
     *
     * Requiere $data['payment_method_code'] (código real de PaymentMethod, no texto libre
     * como antes) y $data['booking'] (SpaBooking, para el snapshot de línea) — si falta
     * cualquiera de los dos, el pago no se registra en absoluto.
     */
    public function registerPayment(int $clientId, float $amount, array $data): Model
    {
        $paymentMethod = PaymentMethod::where('code', $data['payment_method_code'] ?? null)->first();

        if (! $paymentMethod) {
            throw new RuntimeException('Selecciona un método de pago válido.');
        }

        $booking = $data['booking'] ?? null;

        if (! $booking instanceof SpaBooking) {
            throw new RuntimeException('No se pudo determinar la cita de origen para generar el recibo.');
        }

        $destination = $paymentMethod->type === 'cash' ? 'caja' : 'banco';

        $attributes = [
            'client_id' => $clientId,
            'payable_type' => $data['payable_type'] ?? null,
            'payable_id' => $data['payable_id'] ?? null,
            'amount' => $amount,
            'payment_method' => $paymentMethod->name,
            'category' => $data['category'] ?? 'payment',
            'notes' => $data['notes'] ?? null,
        ];

        return DB::transaction(function () use ($attributes, $destination, $paymentMethod, $amount, $data, $booking) {
            $ledgerEntry = $destination === 'banco' ? BankLedger::create($attributes) : CashLedger::create($attributes);

            $this->accountingService->recordBookingPaymentLedger($booking, $ledgerEntry, $paymentMethod, $amount, null, $data['notes'] ?? null);

            return $ledgerEntry;
        });
    }
}
