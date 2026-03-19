<?php

namespace App\Domain\Commercial\Contracts;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;

interface LeadRepositoryInterface
{
    public function findById(int $id): ?Lead;
    public function getAll(): Collection;
    public function create(array $data): Lead;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function convertToClient(int $leadId): bool;
}
