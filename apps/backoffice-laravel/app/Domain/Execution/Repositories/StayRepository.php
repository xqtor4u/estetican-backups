<?php

namespace App\Domain\Execution\Repositories;

use App\Domain\Execution\Contracts\StayRepositoryInterface;
use App\Models\Stay;
use App\Models\ServiceStatusLog;
use App\Models\ServicePhoto;
use Illuminate\Database\Eloquent\Collection;

class StayRepository implements StayRepositoryInterface
{
    public function findById(int $id): ?Stay
    {
        return Stay::find($id);
    }

    public function getActiveStays(): Collection
    {
        return Stay::whereNull('check_out_at')->get();
    }

    public function create(array $data): Stay
    {
        return Stay::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $stay = $this->findById($id);
        if (!$stay) return false;
        
        return $stay->update($data);
    }

    public function addStatusLog(int $stayId, string $status): void
    {
        ServiceStatusLog::create([
            'stay_id' => $stayId,
            'status' => $status
        ]);
    }

    public function addPhoto(int $stayId, string $photoUrl, string $stage, ?string $description): void
    {
        ServicePhoto::create([
            'stay_id' => $stayId,
            'photo_url' => $photoUrl,
            'stage' => $stage,
            'description' => $description
        ]);
    }
}
