<?php

namespace App\Domain\Planning\Contracts;

use App\Models\HotelReservation;
use Illuminate\Database\Eloquent\Collection;

interface HotelReservationRepositoryInterface
{
    public function findById(int $id): ?HotelReservation;
    public function getActive(): Collection;
    public function create(array $data): HotelReservation;
    public function update(int $id, array $data): bool;
    public function updateStatus(int $id, string $status): bool;
}
