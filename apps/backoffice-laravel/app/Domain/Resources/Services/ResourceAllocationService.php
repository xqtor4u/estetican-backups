<?php

namespace App\Domain\Resources\Services;

use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResourceAllocationService implements ResourceAllocationServiceInterface
{
    public function assignResourceToSource(
        int $resourceId,
        Model $source,
        ?int $petId,
        string $startsAt,
        int $usageMinutes,
        ?int $cleanupMinutes = null,
        ?string $notes = null,
    ): ResourceAllocation {
        $usageStartsAt = Carbon::parse($startsAt);
        $usageEndsAt = $usageStartsAt->copy()->addMinutes(max($usageMinutes, 0));

        return $this->createResourceAllocationWindow(
            $resourceId,
            $source,
            $petId,
            $usageStartsAt->toDateTimeString(),
            $usageEndsAt->toDateTimeString(),
            'reserved',
            $cleanupMinutes,
            $notes,
        );
    }

    public function assignResourceWindowToSource(
        int $resourceId,
        Model $source,
        ?int $petId,
        string $startsAt,
        string $endsAt,
        string $allocationType = 'reserved',
        ?int $cleanupMinutes = null,
        ?string $notes = null,
    ): ResourceAllocation {
        return $this->createResourceAllocationWindow(
            $resourceId,
            $source,
            $petId,
            $startsAt,
            $endsAt,
            $allocationType,
            $cleanupMinutes,
            $notes,
        );
    }

    public function syncResourceToSource(
        int $resourceId,
        Model $source,
        ?int $petId,
        string $startsAt,
        int $usageMinutes,
        ?int $cleanupMinutes = null,
        ?string $notes = null,
    ): ResourceAllocation {
        $usageStartsAt = Carbon::parse($startsAt);
        $usageEndsAt = $usageStartsAt->copy()->addMinutes(max($usageMinutes, 0));

        return DB::transaction(function () use ($resourceId, $source, $petId, $usageStartsAt, $usageEndsAt, $cleanupMinutes, $notes): ResourceAllocation {
            $this->releaseSourceAllocations($source);

            return $this->createResourceAllocationWindow(
                $resourceId,
                $source,
                $petId,
                $usageStartsAt->toDateTimeString(),
                $usageEndsAt->toDateTimeString(),
                'reserved',
                $cleanupMinutes,
                $notes,
            );
        });
    }

    public function syncResourceWindowToSource(
        int $resourceId,
        Model $source,
        ?int $petId,
        string $startsAt,
        string $endsAt,
        string $allocationType = 'reserved',
        ?int $cleanupMinutes = null,
        ?string $notes = null,
    ): ResourceAllocation {
        return DB::transaction(function () use ($resourceId, $source, $petId, $startsAt, $endsAt, $allocationType, $cleanupMinutes, $notes): ResourceAllocation {
            $this->releaseSourceAllocations($source);

            return $this->createResourceAllocationWindow(
                $resourceId,
                $source,
                $petId,
                $startsAt,
                $endsAt,
                $allocationType,
                $cleanupMinutes,
                $notes,
            );
        });
    }

    public function releaseSourceAllocations(Model $source): void
    {
        ResourceAllocation::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->delete();
    }

    public function resourceIsAvailable(int $resourceId, string $startsAt, string $endsAt, ?int $ignoreAllocationId = null): bool
    {
        $requestedStartsAt = Carbon::parse($startsAt);
        $requestedEndsAt = Carbon::parse($endsAt);

        if ($requestedEndsAt->lessThanOrEqualTo($requestedStartsAt)) {
            return false;
        }

        $query = ResourceAllocation::query()
            ->where('resource_id', $resourceId)
            ->overlapping($requestedStartsAt, $requestedEndsAt);

        if ($ignoreAllocationId !== null) {
            $query->whereKeyNot($ignoreAllocationId);
        }

        return !$query->exists();
    }

    private function createResourceAllocationWindow(
        int $resourceId,
        Model $source,
        ?int $petId,
        string $startsAt,
        string $endsAt,
        string $allocationType = 'reserved',
        ?int $cleanupMinutes = null,
        ?string $notes = null,
    ): ResourceAllocation {
        $resource = Resource::query()->findOrFail($resourceId);
        $usageStartsAt = Carbon::parse($startsAt);
        $usageEndsAt = Carbon::parse($endsAt);

        if ($usageEndsAt->lessThanOrEqualTo($usageStartsAt)) {
            throw new RuntimeException('La ventana del recurso no es valida.');
        }

        $resolvedCleanupMinutes = max($cleanupMinutes ?? (int) config('backoffice.system.resource_cleaning_buffer_minutes', 15), 0);
        $blockingEndsAt = $usageEndsAt->copy()->addMinutes($resolvedCleanupMinutes);

        if (!$this->resourceIsAvailable($resource->id, $usageStartsAt->toDateTimeString(), $blockingEndsAt->toDateTimeString())) {
            throw new RuntimeException('El recurso seleccionado ya no esta disponible en esa ventana considerando limpieza.');
        }

        return DB::transaction(function () use ($resource, $source, $petId, $usageStartsAt, $usageEndsAt, $blockingEndsAt, $resolvedCleanupMinutes, $notes, $allocationType): ResourceAllocation {
            $primaryAllocation = new ResourceAllocation([
                'allocation_type' => $allocationType,
                'starts_at' => $usageStartsAt,
                'ends_at' => $usageEndsAt,
                'notes' => $notes,
            ]);

            $primaryAllocation->resource()->associate($resource);
            $primaryAllocation->source()->associate($source);

            if ($petId !== null) {
                $primaryAllocation->pet()->associate(Pet::query()->findOrFail($petId));
            }

            $primaryAllocation->save();

            if ($resolvedCleanupMinutes > 0) {
                $cleanupAllocation = new ResourceAllocation([
                    'allocation_type' => 'cleaning',
                    'starts_at' => $usageEndsAt,
                    'ends_at' => $blockingEndsAt,
                    'notes' => 'Bloqueo de limpieza posterior a uso operativo.',
                ]);

                $cleanupAllocation->resource()->associate($resource);
                $cleanupAllocation->source()->associate($source);
                $cleanupAllocation->parentAllocation()->associate($primaryAllocation);

                if ($petId !== null) {
                    $cleanupAllocation->pet()->associate(Pet::query()->findOrFail($petId));
                }

                $cleanupAllocation->save();
            }

            return $primaryAllocation->load(['resource', 'pet', 'childAllocations']);
        });
    }
}