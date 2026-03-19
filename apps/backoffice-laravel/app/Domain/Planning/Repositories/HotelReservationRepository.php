<?php

namespace App\Domain\Planning\Repositories;

use App\Domain\Planning\Contracts\HotelReservationRepositoryInterface;
use App\Models\HotelReservation;
use Illuminate\Database\Eloquent\Collection;

class HotelReservationRepository implements HotelReservationRepositoryInterface
{
    public function findById(int $id): ?HotelReservation
    {
        return HotelReservation::find($id);
    }

    public function getActive(): Collection
    {
        return HotelReservation::where('status', 'scheduled')
            ->where('end_at', '>=', now())
            ->get();
    }

    public function create(array $data): HotelReservation
    {
        return HotelReservation::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $reservation = $this->findById($id);
        if (!$reservation) return false;
        
        return $reservation->update($data);
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }
}
