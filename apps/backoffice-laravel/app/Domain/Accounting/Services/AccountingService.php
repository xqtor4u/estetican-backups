<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Models\Account;
use App\Models\BankLedger;
use App\Models\CashLedger;
use App\Models\Document;
use App\Models\DocumentSeries;
use App\Models\HotelReservation;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountingService implements AccountingServiceInterface
{
    // Cuenta de ingresos de respaldo cuando un servicio no tiene account_id asignado
    private const FALLBACK_INCOME_CODE = '4900';

    public function getNextFolio(int $documentSeriesId): array
    {
        return DB::transaction(function () use ($documentSeriesId) {
            // Bloqueo a nivel de fila para evitar folios duplicados en concurrencia
            $series = DocumentSeries::lockForUpdate()->findOrFail($documentSeriesId);

            $number = $series->next_number;
            $display = $series->formatFolio($number);

            $series->increment('next_number');

            return ['number' => $number, 'display' => $display];
        });
    }

    public function createPaymentEntry(
        Quote $quote,
        PaymentMethod $paymentMethod,
        int $documentSeriesId,
        float $amount,
        User $issuedBy,
        ?string $emailTo = null,
        ?string $reference = null,
        ?string $notes = null
    ): JournalEntry {
        if (! $paymentMethod->account_id) {
            throw new RuntimeException("El método de pago '{$paymentMethod->name}' no tiene una cuenta contable asignada.");
        }

        return DB::transaction(function () use (
            $quote, $paymentMethod, $documentSeriesId,
            $amount, $issuedBy, $emailTo, $reference, $notes
        ) {
            $booking = $quote->spaBooking;
            $client = $booking->pet->client;

            // 1. Folio
            $folio = $this->getNextFolio($documentSeriesId);

            $series = DocumentSeries::find($documentSeriesId);

            // 2. Documento de respaldo
            $document = Document::create([
                'document_series_id' => $documentSeriesId,
                'document_type' => $series->document_type,
                'folio_number' => $folio['number'],
                'folio_display' => $folio['display'],
                'status' => 'emitido',
                'client_id' => $client->id,
                'branch_id' => $issuedBy->branch_id ?? null,
                'issued_by_user_id' => $issuedBy->id,
                'subtotal' => $amount,
                'tax_amount' => 0,
                'total' => $amount,
                'email_to' => $emailTo,
                'gateway_reference' => $reference,
                'documentable_id' => $booking->id,
                'documentable_type' => SpaBooking::class,
            ]);

            // 3. Asiento contable
            $entry = JournalEntry::create([
                'entry_date' => now()->toDateString(),
                'description' => "Cobro {$folio['display']} — {$client->first_name} {$client->last_name}",
                'status' => 'aplicado',
                'document_id' => $document->id,
                'branch_id' => $document->branch_id,
                'created_by_user_id' => $issuedBy->id,
                'posted_by_user_id' => $issuedBy->id,
                'posted_at' => now(),
                'reference_id' => $booking->id,
                'reference_type' => SpaBooking::class,
                'notes' => $notes,
            ]);

            // 4. Líneas de DEBE — una por cuenta de ingresos de cada servicio
            $this->buildDebitLines($entry, $quote, $amount);

            // 5. Línea de HABER — cuenta del método de pago (caja o banco)
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $paymentMethod->account_id,
                'debit' => 0,
                'credit' => $amount,
                'description' => $paymentMethod->name,
            ]);

            return $entry->load('lines.account', 'document');
        });
    }

    public function assignOrderFolio(SpaBooking $booking): ?string
    {
        return $this->assignOrderFolioFor($booking, 'orden_spa');
    }

    public function assignHotelOrderFolio(HotelReservation $reservation): ?string
    {
        return $this->assignOrderFolioFor($reservation, 'orden_hotel');
    }

    private function assignOrderFolioFor(SpaBooking|HotelReservation $record, string $documentType): ?string
    {
        if ($record->order_folio) {
            return $record->order_folio;
        }

        $series = DocumentSeries::where('document_type', $documentType)
            ->where('is_active', true)
            ->first();

        if (! $series) {
            return null;
        }

        $folio = $this->getNextFolio($series->id);

        $record->update([
            'order_series_id' => $series->id,
            'order_folio' => $folio['display'],
        ]);

        return $folio['display'];
    }

    public function recordBookingPayment(
        SpaBooking $booking,
        Payment $payment,
        PaymentMethod $paymentMethod,
        float $amount,
        ?string $reference = null,
        ?string $notes = null
    ): Document {
        return DB::transaction(function () use ($booking, $payment, $paymentMethod, $amount, $reference, $notes) {
            $document = $this->createReceiptDocumentAndEntry($booking, $paymentMethod, $amount, $reference, $notes);

            $payment->update(['document_id' => $document->id]);

            return $document->load('journalEntry.lines.account', 'payment');
        });
    }

    /**
     * Igual que recordBookingPayment(), pero para el camino web de anticipos/liquidación,
     * que registra el dinero en CashLedger/BankLedger en vez de Payment (así lo siguen viendo
     * los reportes existentes que solo leen esas dos tablas, ej. DashboardController).
     */
    public function recordBookingPaymentLedger(
        SpaBooking $booking,
        CashLedger|BankLedger $ledgerEntry,
        PaymentMethod $paymentMethod,
        float $amount,
        ?string $reference = null,
        ?string $notes = null
    ): Document {
        return DB::transaction(function () use ($booking, $ledgerEntry, $paymentMethod, $amount, $reference, $notes) {
            $document = $this->createReceiptDocumentAndEntry($booking, $paymentMethod, $amount, $reference, $notes);

            $ledgerEntry->update(['document_id' => $document->id]);

            return $document->load('journalEntry.lines.account');
        });
    }

    /**
     * Núcleo compartido: crea el Document (folio, snapshot de línea) y su JournalEntry de
     * doble entrada. No liga el dinero (Payment vs CashLedger/BankLedger) — eso lo hace cada
     * método público según qué tabla usa ese camino de cobro.
     */
    private function createReceiptDocumentAndEntry(
        SpaBooking $booking,
        PaymentMethod $paymentMethod,
        float $amount,
        ?string $reference,
        ?string $notes
    ): Document {
        if (! $paymentMethod->account_id) {
            throw new RuntimeException("El método de pago '{$paymentMethod->name}' no tiene una cuenta contable asignada.");
        }

        $series = DocumentSeries::where('is_active', true)
            ->where('document_type', 'recibo')
            ->first();

        if (! $series) {
            throw new RuntimeException('No hay una serie de documentos activa de tipo "recibo". Configúrala en Finanzas → Series de documentos.');
        }

        $issuedBy = auth()->user();
        $client = $booking->pet->client;

        $folio = $this->getNextFolio($series->id);

        $document = Document::create([
            'document_series_id' => $series->id,
            'document_type' => 'recibo',
            'folio_number' => $folio['number'],
            'folio_display' => $folio['display'],
            'status' => 'emitido',
            'client_id' => $client->id,
            'branch_id' => $issuedBy->branch_id ?? null,
            'issued_by_user_id' => $issuedBy->id,
            'subtotal' => $amount,
            'tax_amount' => 0,
            'total' => $amount,
            'gateway_reference' => $reference,
            'documentable_id' => $booking->id,
            'documentable_type' => SpaBooking::class,
            'line_items_snapshot' => $this->snapshotBookingLineItems($booking),
        ]);

        $entry = JournalEntry::create([
            'entry_date' => now()->toDateString(),
            'description' => "Cobro {$folio['display']} — {$client->first_name} {$client->last_name}",
            'status' => 'aplicado',
            'document_id' => $document->id,
            'branch_id' => $document->branch_id,
            'created_by_user_id' => $issuedBy->id,
            'posted_by_user_id' => $issuedBy->id,
            'posted_at' => now(),
            'reference_id' => $booking->id,
            'reference_type' => SpaBooking::class,
            'notes' => $notes,
        ]);

        $this->buildDebitLinesFromBooking($entry, $booking, $amount);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $paymentMethod->account_id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $paymentMethod->name,
        ]);

        return $document;
    }

    /**
     * Snapshot congelado de las líneas de la cita al momento de emitir el recibo — nombre
     * de servicio/artículo como texto (sobrevive aunque el catálogo cambie o se borre después),
     * operador, cantidad, precio de venta y costo externo (BL-075) por separado.
     */
    public function snapshotBookingLineItems(SpaBooking $booking): array
    {
        $booking->load('services.service', 'services.operator', 'items.item');

        $lines = [];

        foreach ($booking->services as $bookingService) {
            $lines[] = [
                'type' => 'service',
                'name' => $bookingService->service?->name ?? '—',
                'quantity' => (float) $bookingService->quantity,
                'price' => (float) $bookingService->current_price,
                'operator_name' => $bookingService->operator?->name,
                'is_external' => (bool) $bookingService->is_external,
                'external_cost' => $bookingService->external_cost !== null ? (float) $bookingService->external_cost : null,
            ];
        }

        foreach ($booking->items as $bookingItem) {
            $lines[] = [
                'type' => 'item',
                'name' => $bookingItem->item?->name ?? '—',
                'quantity' => (float) $bookingItem->quantity,
                'price' => (float) $bookingItem->current_price,
                'operator_name' => null,
                'is_external' => false,
                'external_cost' => null,
            ];
        }

        return $lines;
    }

    public function cancelDocument(Document $document, User $cancelledBy, string $cancellationType, string $reason): void
    {
        if (! in_array($cancellationType, [Document::CANCELLATION_TYPE_CORRECTION, Document::CANCELLATION_TYPE_REFUND], true)) {
            throw new RuntimeException("Tipo de cancelación inválido: {$cancellationType}.");
        }

        if (! $document->isCancellable()) {
            throw new RuntimeException("El documento {$document->folio_display} no se puede cancelar (estado: {$document->status}).");
        }

        DB::transaction(function () use ($document, $cancelledBy, $cancellationType, $reason) {
            $cancelData = [
                'status' => 'cancelado',
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $cancelledBy->id,
                'cancellation_type' => $cancellationType,
                'cancellation_reason' => $reason,
            ];

            $document->update($cancelData);

            if ($cancellationType === Document::CANCELLATION_TYPE_CORRECTION) {
                // El dinero contabilizado sigue siendo correcto — solo el papel estaba mal.
                // El asiento contable NO se toca: seguiría representando fielmente que el
                // dinero sí entró, solo que el documento que lo respaldaba se reemplaza.
                return;
            }

            // Reembolso real: el asiento sí se cancela (el ingreso ya no es tal) y se
            // revierte el dinero en el libro auxiliar real (caja/banco).
            $entry = $document->journalEntry;

            if ($entry) {
                $entry->update([
                    'status' => 'cancelado',
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $cancelledBy->id,
                ]);
            }

            $this->reverseDocumentMoney($document, $cancelledBy, $reason);
        });
    }

    /**
     * Genera la reversión real de dinero (BL-076, rama "reembolso") — una entrada negativa
     * en CashLedger/BankLedger según el destino del pago original, visible en el corte de
     * caja del día como cancelación, nunca oculta ni borrada.
     */
    private function reverseDocumentMoney(Document $document, User $cancelledBy, string $reason): void
    {
        $payment = $document->payment;

        if (! $payment) {
            throw new RuntimeException('No se puede reembolsar: este documento no tiene un pago vinculado del que determinar el destino (caja/banco).');
        }

        $attributes = [
            'client_id' => $payment->client_id,
            'payable_type' => $payment->payable_type,
            'payable_id' => $payment->payable_id,
            'amount' => -1 * abs((float) $payment->amount),
            'payment_method' => $payment->payment_method,
            'category' => 'reembolso_cancelacion',
            'notes' => "Reembolso de {$document->folio_display} — {$reason} (cancelado por {$cancelledBy->name})",
            'created_by_user_id' => $cancelledBy->id,
        ];

        if ($payment->destination === 'banco') {
            BankLedger::create($attributes);
        } else {
            CashLedger::create($attributes);
        }
    }

    /**
     * Construye las líneas de débito agrupando por cuenta contable.
     * Si un servicio/artículo no tiene cuenta asignada usa la cuenta de respaldo 4900.
     */
    private function buildDebitLines(JournalEntry $entry, Quote $quote, float $totalAmount): void
    {
        $fallback = Account::where('code', self::FALLBACK_INCOME_CODE)->first();

        // Agrupar items del quote por cuenta contable
        $byAccount = [];

        foreach ($quote->items as $item) {
            $accountId = $item->service?->account_id ?? $item->item?->account_id ?? $fallback?->id;

            if (! $accountId) {
                throw new RuntimeException('No hay cuenta contable de respaldo (4900). Ejecuta el seeder de cuentas.');
            }

            $price = $item->price_override ?? $item->service?->suggested_price ?? $item->service?->price ?? $item->item?->price ?? 0;
            $byAccount[$accountId] = ($byAccount[$accountId] ?? 0) + ((float) $price * (float) $item->quantity);
        }

        // Si el total calculado por items no cuadra con el monto cobrado (anticipo parcial),
        // registramos el monto real en la cuenta de respaldo
        if (empty($byAccount)) {
            $byAccount[$fallback->id] = $totalAmount;
        } else {
            $itemsTotal = array_sum($byAccount);

            if (round($itemsTotal, 2) !== round($totalAmount, 2)) {
                // Prorratear proporcionalmente
                $ratio = $totalAmount / $itemsTotal;
                foreach ($byAccount as $accountId => $amount) {
                    $byAccount[$accountId] = round($amount * $ratio, 2);
                }
                // Ajustar diferencia de centavos en el primer ítem
                $diff = round($totalAmount - array_sum($byAccount), 2);
                $firstKey = array_key_first($byAccount);
                $byAccount[$firstKey] = round($byAccount[$firstKey] + $diff, 2);
            }
        }

        foreach ($byAccount as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountId,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Ingreso por servicios',
            ]);
        }
    }

    private function buildDebitLinesFromBooking(JournalEntry $entry, SpaBooking $booking, float $totalAmount): void
    {
        $fallback = Account::where('code', self::FALLBACK_INCOME_CODE)->first();
        $byAccount = [];

        $booking->load('services.service', 'items.item');

        foreach ($booking->services as $bookingService) {
            $accountId = $bookingService->service?->account_id ?? $fallback?->id;

            if (! $accountId) {
                throw new RuntimeException('No hay cuenta contable de respaldo (4900). Ejecuta el seeder de cuentas.');
            }

            $price = (float) ($bookingService->current_price ?? 0);
            $byAccount[$accountId] = ($byAccount[$accountId] ?? 0) + $price;
        }

        foreach ($booking->items as $bookingItem) {
            $accountId = $bookingItem->item?->account_id ?? $fallback?->id;

            if (! $accountId) {
                throw new RuntimeException('No hay cuenta contable de respaldo (4900). Ejecuta el seeder de cuentas.');
            }

            $price = (float) ($bookingItem->current_price ?? 0);
            $byAccount[$accountId] = ($byAccount[$accountId] ?? 0) + $price;
        }

        if (empty($byAccount)) {
            if (! $fallback) {
                throw new RuntimeException('No hay cuenta contable de respaldo (4900). Ejecuta el seeder de cuentas.');
            }
            $byAccount[$fallback->id] = $totalAmount;
        } else {
            $itemsTotal = array_sum($byAccount);
            if ($itemsTotal > 0 && round($itemsTotal, 2) !== round($totalAmount, 2)) {
                $ratio = $totalAmount / $itemsTotal;
                foreach ($byAccount as $accountId => $amount) {
                    $byAccount[$accountId] = round($amount * $ratio, 2);
                }
                $diff = round($totalAmount - array_sum($byAccount), 2);
                $firstKey = array_key_first($byAccount);
                $byAccount[$firstKey] = round($byAccount[$firstKey] + $diff, 2);
            }
        }

        foreach ($byAccount as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountId,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Ingreso por servicios',
            ]);
        }
    }
}
