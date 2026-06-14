<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentSeries;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
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

            $number  = $series->next_number;
            $display = $series->formatFolio($number);

            $series->increment('next_number');

            return ['number' => $number, 'display' => $display];
        });
    }

    public function createPaymentEntry(
        Quote         $quote,
        PaymentMethod $paymentMethod,
        int           $documentSeriesId,
        float         $amount,
        User          $issuedBy,
        ?string       $emailTo = null,
        ?string       $reference = null,
        ?string       $notes = null
    ): JournalEntry {
        if (! $paymentMethod->account_id) {
            throw new RuntimeException("El método de pago '{$paymentMethod->name}' no tiene una cuenta contable asignada.");
        }

        return DB::transaction(function () use (
            $quote, $paymentMethod, $documentSeriesId,
            $amount, $issuedBy, $emailTo, $reference, $notes
        ) {
            $booking  = $quote->spaBooking;
            $client   = $booking->pet->client;

            // 1. Folio
            $folio = $this->getNextFolio($documentSeriesId);

            $series = DocumentSeries::find($documentSeriesId);

            // 2. Documento de respaldo
            $document = Document::create([
                'document_series_id'  => $documentSeriesId,
                'document_type'       => $series->document_type,
                'folio_number'        => $folio['number'],
                'folio_display'       => $folio['display'],
                'status'              => 'emitido',
                'client_id'           => $client->id,
                'branch_id'           => $issuedBy->branch_id ?? null,
                'issued_by_user_id'   => $issuedBy->id,
                'subtotal'            => $amount,
                'tax_amount'          => 0,
                'total'               => $amount,
                'email_to'            => $emailTo,
                'gateway_reference'   => $reference,
                'documentable_id'     => $booking->id,
                'documentable_type'   => \App\Models\SpaBooking::class,
            ]);

            // 3. Asiento contable
            $entry = JournalEntry::create([
                'entry_date'          => now()->toDateString(),
                'description'         => "Cobro {$folio['display']} — {$client->first_name} {$client->last_name}",
                'status'              => 'aplicado',
                'document_id'         => $document->id,
                'branch_id'           => $document->branch_id,
                'created_by_user_id'  => $issuedBy->id,
                'posted_by_user_id'   => $issuedBy->id,
                'posted_at'           => now(),
                'reference_id'        => $booking->id,
                'reference_type'      => \App\Models\SpaBooking::class,
                'notes'               => $notes,
            ]);

            // 4. Líneas de DEBE — una por cuenta de ingresos de cada servicio
            $this->buildDebitLines($entry, $quote, $amount);

            // 5. Línea de HABER — cuenta del método de pago (caja o banco)
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $paymentMethod->account_id,
                'debit'            => 0,
                'credit'           => $amount,
                'description'      => $paymentMethod->name,
            ]);

            return $entry->load('lines.account', 'document');
        });
    }

    public function createEntryForBookingPayment(
        SpaBooking    $booking,
        PaymentMethod $paymentMethod,
        float         $amount,
        ?string       $reference = null,
        ?string       $notes = null
    ): ?JournalEntry {
        if (! $paymentMethod->account_id) {
            return null;
        }

        $series = DocumentSeries::where('is_active', true)
            ->where('document_type', 'recibo')
            ->first();

        if (! $series) {
            return null;
        }

        $issuedBy = auth()->user();
        $client   = $booking->pet->client;

        return DB::transaction(function () use (
            $booking, $paymentMethod, $amount, $reference, $notes, $series, $issuedBy, $client
        ) {
            $folio = $this->getNextFolio($series->id);

            $document = Document::create([
                'document_series_id' => $series->id,
                'document_type'      => 'recibo',
                'folio_number'       => $folio['number'],
                'folio_display'      => $folio['display'],
                'status'             => 'emitido',
                'client_id'          => $client->id,
                'branch_id'          => $issuedBy->branch_id ?? null,
                'issued_by_user_id'  => $issuedBy->id,
                'subtotal'           => $amount,
                'tax_amount'         => 0,
                'total'              => $amount,
                'gateway_reference'  => $reference,
                'documentable_id'    => $booking->id,
                'documentable_type'  => SpaBooking::class,
            ]);

            $entry = JournalEntry::create([
                'entry_date'         => now()->toDateString(),
                'description'        => "Cobro {$folio['display']} — {$client->first_name} {$client->last_name}",
                'status'             => 'aplicado',
                'document_id'        => $document->id,
                'branch_id'          => $document->branch_id,
                'created_by_user_id' => $issuedBy->id,
                'posted_by_user_id'  => $issuedBy->id,
                'posted_at'          => now(),
                'reference_id'       => $booking->id,
                'reference_type'     => SpaBooking::class,
                'notes'              => $notes,
            ]);

            $this->buildDebitLinesFromBooking($entry, $booking, $amount);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $paymentMethod->account_id,
                'debit'            => 0,
                'credit'           => $amount,
                'description'      => $paymentMethod->name,
            ]);

            return $entry->load('lines.account', 'document');
        });
    }

    public function cancelEntry(JournalEntry $entry, User $cancelledBy): void
    {
        DB::transaction(function () use ($entry, $cancelledBy) {
            $entry->update(['status' => 'cancelado']);

            if ($entry->document) {
                $entry->document->update(['status' => 'cancelado']);
            }
        });
    }

    /**
     * Construye las líneas de débito agrupando por cuenta contable.
     * Si un servicio no tiene cuenta asignada usa la cuenta de respaldo 4900.
     */
    private function buildDebitLines(JournalEntry $entry, Quote $quote, float $totalAmount): void
    {
        $fallback = Account::where('code', self::FALLBACK_INCOME_CODE)->first();

        // Agrupar items del quote por cuenta contable
        $byAccount = [];

        foreach ($quote->items as $item) {
            $accountId = $item->service->account_id ?? $fallback?->id;

            if (! $accountId) {
                throw new RuntimeException('No hay cuenta contable de respaldo (4900). Ejecuta el seeder de cuentas.');
            }

            $price = $item->price_override ?? $item->service->suggested_price ?? $item->service->price;
            $byAccount[$accountId] = ($byAccount[$accountId] ?? 0) + (float) $price;
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
                'account_id'       => $accountId,
                'debit'            => $amount,
                'credit'           => 0,
                'description'      => 'Ingreso por servicios',
            ]);
        }
    }

    private function buildDebitLinesFromBooking(JournalEntry $entry, SpaBooking $booking, float $totalAmount): void
    {
        $fallback  = Account::where('code', self::FALLBACK_INCOME_CODE)->first();
        $byAccount = [];

        $booking->load('services.service');

        foreach ($booking->services as $bookingService) {
            $accountId = $bookingService->service?->account_id ?? $fallback?->id;

            if (! $accountId) {
                throw new RuntimeException('No hay cuenta contable de respaldo (4900). Ejecuta el seeder de cuentas.');
            }

            $price = (float) ($bookingService->current_price ?? 0);
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
                $diff     = round($totalAmount - array_sum($byAccount), 2);
                $firstKey = array_key_first($byAccount);
                $byAccount[$firstKey] = round($byAccount[$firstKey] + $diff, 2);
            }
        }

        foreach ($byAccount as $accountId => $amount) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $accountId,
                'debit'            => $amount,
                'credit'           => 0,
                'description'      => 'Ingreso por servicios',
            ]);
        }
    }
}
