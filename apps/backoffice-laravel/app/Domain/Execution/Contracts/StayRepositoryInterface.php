<?php

namespace App\Domain\Execution\Contracts;

use App\Models\Stay;
use Illuminate\Database\Eloquent\Collection;

interface StayRepositoryInterface
{
    public function findById(int $id): ?Stay;
    public function getActiveStays(): Collection;
    public function create(array $data): Stay;
    public function update(int $id, array $data): bool;
    public function addStatusLog(int $stayId, string $status): void;
    public function addPhoto(int $stayId, string $photoUrl, string $stage, ?string $description): void;
}
