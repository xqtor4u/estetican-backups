<?php

namespace App\Domain\Commercial\Repositories;

use App\Domain\Commercial\Contracts\PetRepositoryInterface;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Collection;

class PetRepository implements PetRepositoryInterface
{
    public function findById(int $id): ?Pet
    {
        return Pet::find($id);
    }

    public function getByClientId(int $clientId): Collection
    {
        return Pet::where('client_id', $clientId)->get();
    }

    public function create(array $data): Pet
    {
        return Pet::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $pet = $this->findById($id);
        if (!$pet) return false;
        
        return $pet->update($data);
    }

    public function delete(int $id): bool
    {
        $pet = $this->findById($id);
        if (!$pet) return false;
        
        return $pet->delete();
    }
}
