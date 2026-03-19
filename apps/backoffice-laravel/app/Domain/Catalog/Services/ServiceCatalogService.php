<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Contracts\ServiceCatalogServiceInterface;
use App\Domain\Catalog\Contracts\ServiceCatalogRepositoryInterface;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

class ServiceCatalogService implements ServiceCatalogServiceInterface
{
    public function __construct(
        private ServiceCatalogRepositoryInterface $serviceRepository
    ) {}

    public function getService(int $id): ?Service
    {
        return $this->serviceRepository->findById($id);
    }

    public function getAllServices(): Collection
    {
        return $this->serviceRepository->getAll();
    }

    public function getServicesByType(string $type): Collection
    {
        return $this->serviceRepository->getByType($type);
    }

    public function createService(array $data): Service
    {
        return $this->serviceRepository->create($data);
    }

    public function updateService(int $id, array $data): bool
    {
        return $this->serviceRepository->update($id, $data);
    }
}
