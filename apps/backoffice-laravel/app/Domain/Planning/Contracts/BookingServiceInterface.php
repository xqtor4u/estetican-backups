<?php

namespace App\Domain\Planning\Contracts;

use App\Models\SpaBooking;
use Illuminate\Database\Eloquent\Collection;

interface BookingServiceInterface
{
    public function scheduleSpaSession(int $petId, string $scheduledAt, array $services, ?string $notes = null): SpaBooking;
    public function rescheduleBooking(int $bookingId, string $scheduledAt, ?string $notes = null): bool;
    public function cancelBooking(int $bookingId, string $reason): bool;
    public function markNoShow(int $bookingId, ?string $reason = null): bool;
    public function getUpcomingBookings(): Collection;
}
