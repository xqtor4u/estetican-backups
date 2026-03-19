<?php

namespace App\Domain\Execution\Contracts;

use App\Models\ExecutedService;
use Illuminate\Database\Eloquent\Collection;

interface ExecutedServiceRepositoryInterface
{
    public function findById(int $id): ?ExecutedService;
    public function getByPetId(int $petId): Collection;
    public function create(array $data): ExecutedService;
    public function attachItems(int $executedServiceId, array $itemsWithPrices): void;
    public function addStatusLog(int $executedServiceId, string $status): void;
    public function addPhoto(int $executedServiceId, string $photoUrl, string $stage, ?string $description): void;
}
