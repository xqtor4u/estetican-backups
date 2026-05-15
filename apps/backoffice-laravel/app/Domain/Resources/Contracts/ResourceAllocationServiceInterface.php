<?php

namespace App\Domain\Resources\Contracts;

use App\Models\ResourceAllocation;
use Illuminate\Database\Eloquent\Model;

interface ResourceAllocationServiceInterface
{
    public function assignResourceToSource(
        int $resourceId,
        Model $source,
        ?int $petId,
        string $startsAt,
        int $usageMinutes,
        ?int $cleanupMinutes = null,
        ?string $notes = null,
    ): ResourceAllocation;

    public function assignResourceWindowToSource(
        int $resourceId,
        Model $source,
        ?int $petId,
        string $startsAt,
        string $endsAt,
        string $allocationType = 'reserved',
        ?int $cleanupMinutes = null,
        ?string $notes = null,
    ): ResourceAllocation;

    public function resourceIsAvailable(int $resourceId, string $startsAt, string $endsAt, ?int $ignoreAllocationId = null): bool;

    public function syncResourceToSource(
        int $resourceId,
        Model $source,
        ?int $petId,
        string $startsAt,
        int $usageMinutes,
        ?int $cleanupMinutes = null,
        ?string $notes = null,
    ): ResourceAllocation;

    public function syncResourceWindowToSource(
        int $resourceId,
        Model $source,
        ?int $petId,
        string $startsAt,
        string $endsAt,
        string $allocationType = 'reserved',
        ?int $cleanupMinutes = null,
        ?string $notes = null,
    ): ResourceAllocation;

    public function releaseSourceAllocations(Model $source): void;

}