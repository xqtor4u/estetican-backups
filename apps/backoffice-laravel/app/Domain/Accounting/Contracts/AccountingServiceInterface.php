<?php

namespace App\Domain\Accounting\Contracts;

use App\Models\BankLedger;
use App\Models\CashLedger;
use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\Payment;
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
        Quote $quote,
        PaymentMethod $paymentMethod,
        int $documentSeriesId,
        float $amount,
        User $issuedBy,
        ?string $emailTo = null,
        ?string $reference = null,
        ?string $notes = null
    ): JournalEntry;

    /**
     * Asigna el folio de orden de trabajo a un SpaBooking si aún no tiene uno.
     * Busca la primera serie activa de tipo 'orden_spa' y genera el siguiente folio.
     * No hace nada si ya tiene folio o no hay serie configurada.
     */
    public function assignOrderFolio(SpaBooking $booking): ?string;

    /**
     * Registra el recibo real de un cobro de cita (BL-076): crea el Document (folio),
     * el JournalEntry de doble entrada, un snapshot congelado de las líneas (servicio/artículo,
     * operador, costo externo) y liga el Payment ya creado a ese Document.
     * A diferencia del antiguo createEntryForBookingPayment(), es obligatorio — lanza
     * excepción si no hay serie de recibos activa o el método de pago no tiene cuenta contable,
     * en vez de fallar en silencio.
     */
    public function recordBookingPayment(
        SpaBooking $booking,
        Payment $payment,
        PaymentMethod $paymentMethod,
        float $amount,
        ?string $reference = null,
        ?string $notes = null
    ): Document;

    /**
     * Igual que recordBookingPayment(), pero para el camino web de anticipos/liquidación
     * (`QuoteService`), que registra el dinero en CashLedger/BankLedger en vez de Payment —
     * así lo siguen viendo los reportes que hoy solo leen esas dos tablas (ej. DashboardController,
     * "ingresos del día").
     */
    public function recordBookingPaymentLedger(
        SpaBooking $booking,
        CashLedger|BankLedger $ledgerEntry,
        PaymentMethod $paymentMethod,
        float $amount,
        ?string $reference = null,
        ?string $notes = null
    ): Document;

    /**
     * Cancela un documento emitido (recibo). Nunca se borra ni se reutiliza su folio.
     *
     * @param  string  $cancellationType  Document::CANCELLATION_TYPE_CORRECTION (el dinero se
     *                                    queda donde está, solo se corrige el papel — el asiento
     *                                    contable NO se toca, sigue siendo correcto) o
     *                                    Document::CANCELLATION_TYPE_REFUND (reembolso real —
     *                                    también cancela el asiento contable y genera una
     *                                    reversión en CashLedger/BankLedger).
     */
    public function cancelDocument(Document $document, User $cancelledBy, string $cancellationType, string $reason): void;

    /**
     * Snapshot congelado de las líneas actuales de la cita (BL-076) — nombre de servicio/artículo
     * como texto, operador, cantidad, precio y costo externo. Público para reusarse al reemitir
     * un documento (el snapshot se arma desde el estado *actual* de la OT, no desde el viejo).
     */
    public function snapshotBookingLineItems(SpaBooking $booking): array;
}
