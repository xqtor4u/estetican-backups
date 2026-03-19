<?php

namespace App\Domain\Planning\Services;

use App\Domain\Planning\Contracts\HotelReservationServiceInterface;
use App\Domain\Planning\Contracts\HotelReservationRepositoryInterface;
use App\Models\HotelReservation;
use Illuminate\Database\Eloquent\Collection;

class HotelReservationService implements HotelReservationServiceInterface
{
    public function __construct(
        private HotelReservationRepositoryInterface $hotelReservationRepository
    ) {}

    public function reserveHotel(int $petId, string $startAt, string $endAt): HotelReservation
    {
        return $this->hotelReservationRepository->create([
            'pet_id' => $petId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'scheduled'
        ]);
    }

    public function cancelReservation(int $reservationId): bool
    {
        $reservation = $this->hotelReservationRepository->findById($reservationId);
        if (!$reservation || $reservation->status !== 'scheduled') {
            return false;
        }

        return $this->hotelReservationRepository->updateStatus($reservationId, 'cancelled');
    }

    public function getActiveReservations(): Collection
    {
        return $this->hotelReservationRepository->getActive();
    }
}
