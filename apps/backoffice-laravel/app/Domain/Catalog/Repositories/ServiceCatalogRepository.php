<?php

namespace App\Domain\Catalog\Repositories;

use App\Domain\Catalog\Contracts\ServiceCatalogRepositoryInterface;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

class ServiceCatalogRepository implements ServiceCatalogRepositoryInterface
{
    public function findById(int $id): ?Service
    {
        return Service::find($id);
    }

    public function getAll(): Collection
    {
        return Service::all();
    }

    public function getByType(string $type): Collection
    {
        return Service::where('type', $type)->get();
    }

    public function getActive(): Collection
    {
        return Service::where('is_active', true)->get();
    }

    public function getAssistantVisible(): Collection
    {
        return Service::where('is_active', true)->where('ai_visible', true)->get();
    }

    public function create(array $data): Service
    {
        return Service::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $service = $this->findById($id);
        if (! $service) {
            return false;
        }

        return $service->update($data);
    }

    public function delete(int $id): bool
    {
        $service = $this->findById($id);
        if (! $service) {
            return false;
        }

        return $service->delete();
    }
}
