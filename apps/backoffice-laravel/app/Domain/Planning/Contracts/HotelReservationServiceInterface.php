<?php

namespace App\Domain\Planning\Contracts;

use App\Models\HotelReservation;
use Illuminate\Database\Eloquent\Collection;

interface HotelReservationServiceInterface
{
    public function reserveHotel(int $petId, string $startAt, string $endAt): HotelReservation;
    public function cancelReservation(int $reservationId): bool;
    public function getActiveReservations(): Collection;
}
