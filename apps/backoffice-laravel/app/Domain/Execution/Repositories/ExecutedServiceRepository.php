<?php

namespace App\Domain\Execution\Repositories;

use App\Domain\Execution\Contracts\ExecutedServiceRepositoryInterface;
use App\Models\ExecutedService;
use App\Models\ExecutedServiceItem;
use App\Models\Service;
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
            $service = Service::find($serviceId);

            ExecutedServiceItem::create([
                'executed_service_id' => $executedServiceId,
                'service_id' => $serviceId,
                'service_name_snapshot' => $service?->name ?? "Servicio #{$serviceId}",
                'service_description_snapshot' => $service?->description,
                'service_type_snapshot' => $service?->type,
                'charged_price' => $price,
                'duration_minutes_snapshot' => $service?->suggested_duration_minutes ?? $service?->duration_minutes,
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
