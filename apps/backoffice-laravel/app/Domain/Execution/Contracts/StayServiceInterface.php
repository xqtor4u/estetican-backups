<?php

namespace App\Domain\Execution\Contracts;

use App\Models\Stay;
use Illuminate\Database\Eloquent\Collection;

interface StayServiceInterface
{
    public function checkIn(int $petId, ?int $hotelReservationId, ?string $notes): Stay;
    public function checkOut(int $stayId): bool;
    public function updateStayStatus(int $stayId, string $status): void;
    public function uploadStayPhoto(int $stayId, string $photoUrl, string $stage, ?string $description): void;
}
