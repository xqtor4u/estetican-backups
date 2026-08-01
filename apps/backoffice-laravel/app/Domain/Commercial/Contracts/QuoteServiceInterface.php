<?php

namespace App\Domain\Commercial\Contracts;

use App\Models\Quote;
use App\Models\SpaBooking;
use Illuminate\Database\Eloquent\Model;

interface QuoteServiceInterface
{
    /**
     * Create a new quote option for a booking.
     */
    public function createQuoteFromBooking(SpaBooking $booking, array $data): Quote;

    /**
     * Mark a quote as accepted and trigger the work order process.
     */
    public function acceptQuote(Quote $quote, array $acceptanceData): Quote;

    /**
     * Mark a quote as rejected.
     */
    public function rejectQuote(Quote $quote, ?string $reason = null): Quote;

    /**
     * Register a payment (advance or full) linked to a quote or booking. BL-076: $data debe
     * traer 'payment_method_code' (código real de PaymentMethod) y 'booking' (SpaBooking) —
     * genera también el recibo real (Document + JournalEntry), ya no es opcional.
     */
    public function registerPayment(int $clientId, float $amount, array $data): Model;
}
