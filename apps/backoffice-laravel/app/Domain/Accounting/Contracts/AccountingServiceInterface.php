<?php

namespace App\Domain\Accounting\Contracts;

use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\SpaBooking;
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
     * Asigna el folio de orden de trabajo a un SpaBooking si aún no tiene uno.
     * Busca la primera serie activa de tipo 'orden_spa' y genera el siguiente folio.
     * No hace nada si ya tiene folio o no hay serie configurada.
     */
    public function assignOrderFolio(SpaBooking $booking): ?string;

    /**
     * Crea un asiento contable para un cobro de cita, sin requerir un Quote formal.
     * Selecciona automáticamente la primera serie de recibos activa.
     * Devuelve null si no hay serie configurada o el método no tiene cuenta.
     */
    public function createEntryForBookingPayment(
        SpaBooking    $booking,
        PaymentMethod $paymentMethod,
        float         $amount,
        ?string       $reference = null,
        ?string       $notes = null
    ): ?JournalEntry;

    /**
     * Cancela un asiento y su documento de respaldo.
     */
    public function cancelEntry(JournalEntry $entry, User $cancelledBy): void;
}
