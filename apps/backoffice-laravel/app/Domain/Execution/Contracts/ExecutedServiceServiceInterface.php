<?php

namespace App\Domain\Execution\Contracts;

use App\Models\ExecutedService;

interface ExecutedServiceServiceInterface
{
    /**
     * Converts a planned SpaBooking into a real ExecutedService.
     */
    public function convertFromBooking(int $spaBookingId, array $finalItemsWithPrices, ?string $notes): ExecutedService;
    
    public function updateServiceStatus(int $executedServiceId, string $status): void;
    public function uploadServicePhoto(int $executedServiceId, string $photoUrl, string $stage, ?string $description): void;
}
