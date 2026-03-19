<?php

namespace App\Domain\Execution\Repositories;

use App\Domain\Execution\Contracts\ExecutedServiceRepositoryInterface;
use App\Models\ExecutedService;
use App\Models\ExecutedServiceItem;
use App\Models\ServiceStatusLog;
use App\Models\ServicePhoto;
use Illuminate\Database\Eloquent\Collection;

class ExecutedServiceRepository implements ExecutedServiceRepositoryInterface
{
    public function findById(int $id): ?ExecutedService
    {
        return ExecutedService::find($id);
    }

    public function getByPetId(int $petId): Collection
    {
        return ExecutedService::where('pet_id', $petId)->get();
    }

    public function create(array $data): ExecutedService
    {
        return ExecutedService::create($data);
    }

    public function attachItems(int $executedServiceId, array $itemsWithPrices): void
    {
        foreach ($itemsWithPrices as $serviceId => $price) {
            ExecutedServiceItem::create([
                'executed_service_id' => $executedServiceId,
                'service_id' => $serviceId,
                'charged_price' => $price
            ]);
        }
    }

    public function addStatusLog(int $executedServiceId, string $status): void
    {
        ServiceStatusLog::create([
            'executed_service_id' => $executedServiceId,
            'status' => $status
        ]);
    }

    public function addPhoto(int $executedServiceId, string $photoUrl, string $stage, ?string $description): void
    {
        ServicePhoto::create([
            'executed_service_id' => $executedServiceId,
            'photo_url' => $photoUrl,
            'stage' => $stage,
            'description' => $description
        ]);
    }
}
