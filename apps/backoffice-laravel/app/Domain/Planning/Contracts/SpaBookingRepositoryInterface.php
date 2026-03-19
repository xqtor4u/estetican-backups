<?php

namespace App\Domain\Planning\Contracts;

use App\Models\SpaBooking;
use Illuminate\Database\Eloquent\Collection;

interface SpaBookingRepositoryInterface
{
    public function findById(int $id): ?SpaBooking;
    public function getUpcoming(): Collection;
    public function create(array $data): SpaBooking;
    public function update(int $id, array $data): bool;
    public function attachServices(int $bookingId, array $serviceIdsWithPrices): void;
    public function updateStatus(int $id, string $status): bool;
}
