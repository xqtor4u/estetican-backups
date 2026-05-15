<?php

namespace App\Domain\Planning\Services;

use App\Domain\Planning\Contracts\BookingServiceInterface;
use App\Domain\Planning\Contracts\SpaBookingRepositoryInterface;
use App\Models\SpaBooking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class BookingService implements BookingServiceInterface
{
    public function __construct(
        private SpaBookingRepositoryInterface $spaBookingRepository
    ) {}

    public function scheduleSpaSession(int $petId, string $scheduledAt, array $services, ?string $notes = null): SpaBooking
    {
        DB::beginTransaction();
        try {
            $totalPrice = 0;
            // services array format: [service_id => price, ...]
            foreach ($services as $price) {
                $totalPrice += $price;
            }

            $booking = $this->spaBookingRepository->create([
                'pet_id' => $petId,
                'scheduled_at' => $scheduledAt,
                'total_estimated_price' => $totalPrice,
                'status' => 'scheduled',
                'notes' => $notes,
            ]);

            $this->spaBookingRepository->attachServices($booking->id, $services);
            
            DB::commit();
            return $booking;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function rescheduleBooking(int $bookingId, string $scheduledAt, ?string $notes = null): bool
    {
        $booking = $this->spaBookingRepository->findById($bookingId);

        if (!$booking || $booking->status !== 'scheduled') {
            return false;
        }

        return $this->spaBookingRepository->update($bookingId, [
            'scheduled_at' => $scheduledAt,
            'notes' => $notes,
        ]);
    }

    public function cancelBooking(int $bookingId, string $reason): bool
    {
        $booking = $this->spaBookingRepository->findById($bookingId);
        if (!$booking || $booking->status !== 'scheduled') {
            return false;
        }

        return $this->spaBookingRepository->update($bookingId, [
            'status' => 'cancelled',
            'cancellation_reason' => $reason
        ]);
    }

    public function markNoShow(int $bookingId, ?string $reason = null): bool
    {
        $booking = $this->spaBookingRepository->findById($bookingId);

        if (!$booking || $booking->status !== 'scheduled') {
            return false;
        }

        return $this->spaBookingRepository->update($bookingId, [
            'status' => 'no_show',
            'cancellation_reason' => $reason,
        ]);
    }

    public function getUpcomingBookings(): Collection
    {
        return $this->spaBookingRepository->getUpcoming();
    }
}
