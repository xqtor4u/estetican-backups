<?php

namespace App\Domain\Commercial\Contracts;

use App\Models\Quote;
use App\Models\SpaBooking;

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
     * Register a payment (advance or full) linked to a quote or booking.
     */
    public function registerPayment(int $clientId, float $amount, array $data): \Illuminate\Database\Eloquent\Model;
}
