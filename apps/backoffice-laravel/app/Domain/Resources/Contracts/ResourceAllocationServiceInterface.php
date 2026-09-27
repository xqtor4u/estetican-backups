<?php

namespace App\Domain\Resources\Contracts;

use App\Models\ResourceAllocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

interface ResourceAllocationServiceInterface
{
    /**
     * Estado de ocupación de un recurso (jaula) para una ventana propuesta: si
     * [inicio, fin + limpieza] pisa alguna asignación existente, más la ocupación del día
     * completo para pintar una barra visual (agenda/create.blade.php, y desde ZEUS-043
     * también los endpoints de `/api/resources/{resource}/availability` para `tstmov`).
     * Movido desde `SpaBookingController::resourceAvailabilitySummary()` (privado) — mismo
     * comportamiento exacto, solo compartido entre el controlador web y el de API.
     */
    public function availabilitySummary(int $resourceId, Carbon $windowStart, Carbon $windowEnd, ?int $excludeBookingId = null): array;

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
