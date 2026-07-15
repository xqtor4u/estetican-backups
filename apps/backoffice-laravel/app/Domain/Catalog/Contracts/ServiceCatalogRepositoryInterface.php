<?php

namespace App\Domain\Catalog\Contracts;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

interface ServiceCatalogRepositoryInterface
{
    public function findById(int $id): ?Service;

    public function getAll(): Collection;

    public function getByType(string $type): Collection;

    public function getActive(): Collection;

    public function getAssistantVisible(): Collection;

    public function create(array $data): Service;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
