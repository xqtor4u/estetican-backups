<?php

namespace App\Domain\Planning\Contracts;

use App\Models\SpaBooking;
use Illuminate\Database\Eloquent\Collection;

interface BookingServiceInterface
{
    public function scheduleSpaSession(int $petId, string $scheduledAt, array $services): SpaBooking;
    public function cancelBooking(int $bookingId, string $reason): bool;
    public function getUpcomingBookings(): Collection;
}
