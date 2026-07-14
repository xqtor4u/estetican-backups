<?php

namespace App\Domain\Catalog\Contracts;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

interface ServiceCatalogServiceInterface
{
    public function getService(int $id): ?Service;

    public function getAllServices(): Collection;

    public function getServicesByType(string $type): Collection;

    public function getActiveServices(): Collection;

    public function createService(array $data): Service;

    public function updateService(int $id, array $data): bool;
}
