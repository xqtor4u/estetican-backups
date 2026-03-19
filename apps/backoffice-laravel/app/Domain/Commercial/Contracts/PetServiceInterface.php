<?php

namespace App\Domain\Commercial\Contracts;

use App\Models\Pet;
use Illuminate\Database\Eloquent\Collection;

interface PetServiceInterface
{
    public function getPet(int $id): ?Pet;
    public function getPetsByClient(int $clientId): Collection;
    public function registerPet(array $data): Pet;
    public function updatePetInfo(int $id, array $data): bool;
}
