<?php

namespace App\Domain\Execution\Services;

use App\Domain\Execution\Contracts\ExecutedServiceServiceInterface;
use App\Domain\Execution\Contracts\ExecutedServiceRepositoryInterface;
use App\Domain\Planning\Contracts\SpaBookingRepositoryInterface;
use App\Models\ExecutedService;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Exception;

class ExecutedServiceService implements ExecutedServiceServiceInterface
{
    public function __construct(
        private ExecutedServiceRepositoryInterface $executedServiceRepository,
        private SpaBookingRepositoryInterface $spaBookingRepository
    ) {}

    public function convertFromBooking(
        int $spaBookingId,
        array $finalItemsWithPrices,
        ?int $operatorId = null,
        ?string $serviceSummary = null,
        ?string $notes = null
    ): ExecutedService
    {
        DB::beginTransaction();
        try {
            $booking = $this->spaBookingRepository->findById($spaBookingId);
            if (!$booking) {
                throw new Exception("Booking not found");
            }

            if ($operatorId !== null && !Operator::query()->whereKey($operatorId)->exists()) {
                throw new Exception("Operator not found");
            }

            $totalFinalPrice = 0;
            foreach ($finalItemsWithPrices as $price) {
                $totalFinalPrice += $price;
            }

            $executedService = $this->executedServiceRepository->create([
                'spa_booking_id' => $spaBookingId,
                'pet_id' => $booking->pet_id,
                'operator_id' => $operatorId,
                'final_price' => $totalFinalPrice,
                'service_summary' => $serviceSummary,
                'notes' => $notes,
                'executed_at' => now()
            ]);

            $this->executedServiceRepository->attachItems($executedService->id, $finalItemsWithPrices);
            $this->spaBookingRepository->updateStatus($spaBookingId, 'completed');
            $this->updateServiceStatus($executedService->id, 'completed');

            DB::commit();
            return $executedService;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateServiceStatus(int $executedServiceId, string $status): void
    {
        $this->executedServiceRepository->addStatusLog($executedServiceId, $status);
    }

    public function uploadServicePhoto(int $executedServiceId, string $photoUrl, string $stage, ?string $description): void
    {
        $this->executedServiceRepository->addPhoto($executedServiceId, $photoUrl, $stage, $description);
    }
}
