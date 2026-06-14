<?php

namespace App\Domain\Accounting\Contracts;

use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\User;

interface AccountingServiceInterface
{
    /**
     * Genera el folio siguiente para una serie de documentos (con bloqueo para evitar duplicados).
     */
    public function getNextFolio(int $documentSeriesId): array; // ['number' => int, 'display' => string]

    /**
     * Crea el documento de respaldo y el asiento contable de doble entrada para un cobro.
     * Líneas de debe: una por cuenta de ingreso de cada servicio del quote.
     * Línea de haber: cuenta contable del método de pago.
     */
    public function createPaymentEntry(
        Quote         $quote,
        PaymentMethod $paymentMethod,
        int           $documentSeriesId,
        float         $amount,
        User          $issuedBy,
        ?string       $emailTo = null,
        ?string       $reference = null,
        ?string       $notes = null
    ): JournalEntry;

    /**
     * Cancela un asiento y su documento de respaldo.
     */
    public function cancelEntry(JournalEntry $entry, User $cancelledBy): void;
}
