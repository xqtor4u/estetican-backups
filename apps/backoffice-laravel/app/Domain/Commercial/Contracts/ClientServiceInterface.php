<?php

namespace App\Domain\Commercial\Contracts;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

interface ClientServiceInterface
{
    public function getClient(int $id): ?Client;
    public function getAllClients(): Collection;
    public function registerClient(array $data): Client;
    public function updateClientInfo(int $id, array $data): bool;
}
