<?php

namespace App\Domain\Execution\Services;

use App\Domain\Execution\Contracts\StayServiceInterface;
use App\Domain\Execution\Contracts\StayRepositoryInterface;
use App\Models\Stay;

class StayService implements StayServiceInterface
{
    public function __construct(
        private StayRepositoryInterface $stayRepository
    ) {}

    public function checkIn(int $petId, ?int $hotelReservationId, ?string $notes): Stay
    {
        $stay = $this->stayRepository->create([
            'pet_id' => $petId,
            'hotel_reservation_id' => $hotelReservationId,
            'check_in_at' => now(),
            'notes' => $notes
        ]);

        $this->updateStayStatus($stay->id, 'checked_in');

        return $stay;
    }

    public function checkOut(int $stayId): bool
    {
        $success = $this->stayRepository->update($stayId, [
            'check_out_at' => now()
        ]);

        if ($success) {
            $this->updateStayStatus($stayId, 'checked_out');
        }

        return $success;
    }

    public function updateStayStatus(int $stayId, string $status): void
    {
        $this->stayRepository->addStatusLog($stayId, $status);
    }

    public function uploadStayPhoto(int $stayId, string $photoUrl, string $stage, ?string $description): void
    {
        $this->stayRepository->addPhoto($stayId, $photoUrl, $stage, $description);
    }
}
