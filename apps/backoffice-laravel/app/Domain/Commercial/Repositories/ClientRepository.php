<?php

namespace App\Domain\Commercial\Repositories;

use App\Domain\Commercial\Contracts\ClientRepositoryInterface;
use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

class ClientRepository implements ClientRepositoryInterface
{
    public function findById(int $id): ?Client
    {
        return Client::with('pets')->find($id);
    }

    public function getAll(): Collection
    {
        return Client::all();
    }

    public function create(array $data): Client
    {
        return Client::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $client = $this->findById($id);
        if (!$client) return false;
        
        return $client->update($data);
    }

    public function delete(int $id): bool
    {
        $client = $this->findById($id);
        if (!$client) return false;
        
        return $client->delete();
    }
}
