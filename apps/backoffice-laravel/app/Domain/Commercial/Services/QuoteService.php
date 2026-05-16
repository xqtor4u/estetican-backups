<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Contracts\QuoteServiceInterface;
use App\Models\CashLedger;
use App\Models\BankLedger;
use Illuminate\Database\Eloquent\Model;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\SpaBooking;
use Illuminate\Support\Facades\DB;

class QuoteService implements QuoteServiceInterface
{
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
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $quoteItem = new QuoteItem([
                        'quote_id' => $quote->id,
                        'service_id' => $item['service_id'],
                        'price_override' => $item['price'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                    $quoteItem->save();

                    // Calculate total
                    $price = $item['price'] ?? \App\Models\Service::find($item['service_id'])->price;
                    $total += $price;
                }
            }

            $quote->update(['total_amount' => $total]);

            return $quote->load('items.service');
        });
    }

    /**
     * Mark a quote as accepted and trigger the work order process.
     */
    public function acceptQuote(Quote $quote, array $acceptanceData): Quote
    {
        return DB::transaction(function () use ($quote, $acceptanceData) {
            // 1. Mark this quote as accepted
            $quote->update([
                'status' => 'accepted',
                'advance_amount' => $acceptanceData['advance_amount'] ?? 0,
                'advance_payment_method' => $acceptanceData['advance_payment_method'] ?? null,
            ]);

            // 2. Reject other quotes for the same booking
            Quote::query()
                ->where('spa_booking_id', $quote->spa_booking_id)
                ->where('id', '!=', $quote->id)
                ->update(['status' => 'rejected']);

            // 3. Register the advance payment if present
            if (($acceptanceData['advance_amount'] ?? 0) > 0) {
                $method = $acceptanceData['advance_payment_method'] ?? 'Efectivo';
                $dest = (in_array($method, ['Tarjeta', 'Transferencia'])) ? 'banco' : 'caja';
                
                $this->registerPayment($quote->spaBooking->pet->client_id, $acceptanceData['advance_amount'], [
                    'payable_type' => Quote::class,
                    'payable_id' => $quote->id,
                    'payment_method' => $method,
                    'destination' => $acceptanceData['destination'] ?? $dest,
                    'category' => 'advance',
                    'notes' => 'Anticipo registrado al aceptar presupuesto.',
                ]);
            }

            // 4. Transform Booking status to Work Order + sync services from accepted quote
            $quote->loadMissing('items.service');
            $booking = $quote->spaBooking;
            $booking->services()->delete();
            foreach ($quote->items as $item) {
                $booking->services()->create([
                    'service_id'    => $item->service_id,
                    'current_price' => $item->price_override ?? $item->service?->price ?? 0,
                ]);
            }
            $booking->update([
                'status'                  => 'work_order',
                'total_estimated_price'   => $quote->total_amount,
            ]);

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
            'notes' => $quote->notes . ($reason ? "\nRechazo: $reason" : ""),
        ]);

        return $quote;
    }

    /**
     * Register a payment (advance or full) linked to a quote or booking.
     */
    public function registerPayment(int $clientId, float $amount, array $data): Model
    {
        $destination = $data['destination'] ?? 'caja';
        
        $attributes = [
            'client_id' => $clientId,
            'payable_type' => $data['payable_type'] ?? null,
            'payable_id' => $data['payable_id'] ?? null,
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? 'Efectivo',
            'category' => $data['category'] ?? 'payment',
            'notes' => $data['notes'] ?? null,
        ];

        if ($destination === 'banco') {
            return BankLedger::create($attributes);
        }

        return CashLedger::create($attributes);
    }
}
