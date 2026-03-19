<?php

namespace App\Domain\Commercial\Contracts;

use App\Models\Pet;
use Illuminate\Database\Eloquent\Collection;

interface PetRepositoryInterface
{
    public function findById(int $id): ?Pet;
    public function getByClientId(int $clientId): Collection;
    public function create(array $data): Pet;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
