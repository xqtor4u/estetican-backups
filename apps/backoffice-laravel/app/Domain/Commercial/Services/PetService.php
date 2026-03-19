<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Contracts\PetServiceInterface;
use App\Domain\Commercial\Contracts\PetRepositoryInterface;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Collection;

class PetService implements PetServiceInterface
{
    public function __construct(
        private PetRepositoryInterface $petRepository
    ) {}

    public function getPet(int $id): ?Pet
    {
        return $this->petRepository->findById($id);
    }

    public function getPetsByClient(int $clientId): Collection
    {
        return $this->petRepository->getByClientId($clientId);
    }

    public function registerPet(array $data): Pet
    {
        return $this->petRepository->create($data);
    }

    public function updatePetInfo(int $id, array $data): bool
    {
        return $this->petRepository->update($id, $data);
    }
}
