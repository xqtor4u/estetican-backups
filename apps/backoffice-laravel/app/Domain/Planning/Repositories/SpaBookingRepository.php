<?php

namespace App\Domain\Planning\Repositories;

use App\Domain\Planning\Contracts\SpaBookingRepositoryInterface;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SpaBookingRepository implements SpaBookingRepositoryInterface
{
    public function findById(int $id): ?SpaBooking
    {
        return SpaBooking::find($id);
    }

    public function getUpcoming(): Collection
    {
        return SpaBooking::where('scheduled_at', '>=', now())
            ->whereIn('status', ['scheduled'])
            ->get();
    }

    public function create(array $data): SpaBooking
    {
        return SpaBooking::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $booking = $this->findById($id);
        if (!$booking) return false;
        
        return $booking->update($data);
    }

    public function attachServices(int $bookingId, array $serviceIdsWithPrices): void
    {
        foreach ($serviceIdsWithPrices as $serviceId => $price) {
            SpaBookingService::create([
                'spa_booking_id' => $bookingId,
                'service_id' => $serviceId,
                'current_price' => $price
            ]);
        }
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }
}
