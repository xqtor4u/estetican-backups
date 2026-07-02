<?php

namespace App\Domain\Planning\Services;

use App\Models\SpaBooking;
use Carbon\Carbon;

class OperatorAvailabilityChecker
{
    /**
     * Alcance intencional: solo SpaBooking. `operator_id` no existe en HotelReservation.
     */
    public function hasConflict(int $operatorId, Carbon $start, int $durationMinutes, ?int $excludeBookingId = null): bool
    {
        $end = $start->copy()->addMinutes(max($durationMinutes, 0));

        return SpaBooking::where('operator_id', $operatorId)
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('scheduled_at', '<', $end)
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL COALESCE(duration_minutes, 0) MINUTE) > ?', [$start])
            ->exists();
    }
}
