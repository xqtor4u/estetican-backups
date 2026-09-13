<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Domain\Commercial\Contracts\QuoteServiceInterface;
use App\Models\Item;
use App\Models\Payment;
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
                    $catalogRecord = ! empty($item['item_id'])
                        ? Item::find($item['item_id'])
                        : Service::find($item['service_id']);
                    $unitPrice = $item['price'] ?? $catalogRecord?->price ?? 0;

                    $quoteItem = new QuoteItem([
                        'quote_id' => $quote->id,
                        'service_id' => $item['service_id'] ?? null,
                        'item_id' => $item['item_id'] ?? null,
                        'group_id' => $item['group_id'] ?? null,
                        'quantity' => $quantity,
                        // SYNC-105 (portado desde Zeus/ZEUS-037): precio real cotizado, SIEMPRE
                        // congelado — antes solo se guardaba si el frontend mandaba un override
                        // explícito, y si no, unitPrice()/lineTotal() leían el precio EN VIVO del
                        // catálogo cada vez que se reimprimía o aceptaba el presupuesto.
                        'price_override' => $unitPrice,
                        'name_snapshot' => $catalogRecord?->name,
                        'description_snapshot' => $catalogRecord?->description,
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
                        'item_name_snapshot' => $item->name_snapshot ?? $item->item?->name,
                        'group_id' => $item->group_id,
                        'quantity' => $item->quantity,
                        'current_price' => $lineTotal,
                    ]);
                } else {
                    $booking->services()->create([
                        'service_id' => $item->service_id,
                        'service_name_snapshot' => $item->name_snapshot ?? $item->service?->name,
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
     * Registra un pago (anticipo o liquidación) de una cita — SYNC-098: el dinero se guarda
     * SIEMPRE en `payments` (tabla única canónica), ligado al SpaBooking, igual que el cobro
     * móvil. Genera también el recibo real (Document + JournalEntry) de forma obligatoria y
     * transaccional (BL-076). Antes este camino escribía CashLedger/BankLedger en paralelo,
     * lo que obligaba a 6 sitios de lectura a mezclar 3 fuentes a mano.
     *
     * Requiere $data['payment_method_code'] y $data['booking'] (SpaBooking) — si falta
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

        return DB::transaction(function () use ($clientId, $amount, $data, $destination, $paymentMethod, $booking) {
            $payment = Payment::create([
                'client_id' => $clientId,
                'payable_type' => SpaBooking::class,
                'payable_id' => $booking->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod->name,
                'destination' => $destination,
                'category' => $this->normalizePaymentCategory($data['category'] ?? null),
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
            ]);

            $this->accountingService->recordBookingPayment($booking, $payment, $paymentMethod, $amount, null, $data['notes'] ?? null);

            return $payment;
        });
    }

    /**
     * Vocabulario único de `payments.category` para el camino web: `advance` (anticipo al
     * aceptar presupuesto), `misc_charge` (cargo suelto) o `liquidacion` (todo lo demás).
     */
    private function normalizePaymentCategory(?string $category): string
    {
        return match ($category) {
            'advance' => 'advance',
            'misc_charge', 'misc' => 'misc_charge',
            default => 'liquidacion',
        };
    }
}
