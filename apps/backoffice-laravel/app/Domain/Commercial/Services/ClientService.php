<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Contracts\ClientServiceInterface;
use App\Domain\Commercial\Contracts\ClientRepositoryInterface;
use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

class ClientService implements ClientServiceInterface
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository
    ) {}

    public function getClient(int $id): ?Client
    {
        return $this->clientRepository->findById($id);
    }

    public function getAllClients(): Collection
    {
        return $this->clientRepository->getAll();
    }

    public function registerClient(array $data): Client
    {
        return $this->clientRepository->create($data);
    }

    public function updateClientInfo(int $id, array $data): bool
    {
        return $this->clientRepository->update($id, $data);
    }
}
