<?php

namespace App\Domain\Commercial\Contracts;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

interface ClientRepositoryInterface
{
    public function findById(int $id): ?Client;
    public function getAll(): Collection;
    public function create(array $data): Client;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
