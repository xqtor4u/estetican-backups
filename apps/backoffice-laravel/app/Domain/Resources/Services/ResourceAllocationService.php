<?php

namespace App\Domain\Resources\Services;

use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\SpaBooking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        return ! $query->exists();
    }

    /**
     * Estado de ocupación de una jaula/recurso para la ventana de estancia: si
     * [inicio, fin + limpieza] pisa alguna asignación existente, y esas asignaciones para
     * listarlas. La estancia es independiente del servicio — arranca con él pero puede
     * terminar más tarde, incluso otro día (el perro se queda a dormir) — ver
     * `resource_starts_at`/`resource_ends_at`. Movido desde
     * `SpaBookingController::resourceAvailabilitySummary()` (era `private` ahí, sin uso
     * fuera del controlador web) para que `Api\BookingController`/los endpoints de
     * `/api/resources/*` (ZEUS-043) puedan reusarlo sin duplicar la lógica.
     */
    public function availabilitySummary(int $resourceId, Carbon $windowStart, Carbon $windowEnd, ?int $excludeBookingId = null): array
    {
        $buffer = (int) config('backoffice.system.resource_cleaning_buffer_minutes', 15);
        $blockingEnd = $windowEnd->copy()->addMinutes($buffer);
        $excludeClause = fn ($q) => $q->when($excludeBookingId, fn ($qq) => $qq
            ->where(fn ($qqq) => $qqq
                ->where('source_type', '!=', (new SpaBooking)->getMorphClass())
                ->orWhere('source_id', '!=', $excludeBookingId)));

        // Solape directo contra la ventana de estancia elegida (puede abarcar varios días) —
        // determina si ESTA estancia en particular choca con algo: `available` y el aviso
        // "⚠ Jaula ocupada" salen de aquí, acotado a lo que de verdad se pisa con lo propuesto.
        $windowAllocations = $excludeClause(ResourceAllocation::query()
            ->where('resource_id', $resourceId)
            ->where('starts_at', '<', $blockingEnd)
            ->where('ends_at', '>', $windowStart))
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at']);

        // Ocupación del DÍA completo de la jaula — mismo criterio que `daySummaryFor` del
        // operador (día completo, no solo lo que choca con la ventana propuesta) — para que la
        // barra visual, dibujada a la misma escala que la del operador, muestre de verdad las
        // reservas de ese día. Antes esta función solo devolvía lo que se solapaba con la
        // ventana de estancia propuesta, así que la barra casi nunca mostraba nada real (solo
        // por pura casualidad si el horario por defecto ya caía encima de una reserva).
        $dayStart = $windowStart->copy()->startOfDay();
        $dayEnd = $windowStart->copy()->endOfDay();
        $dayAllocations = $excludeClause(ResourceAllocation::query()
            ->where('resource_id', $resourceId)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart))
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at']);

        $resource = Resource::find($resourceId);
        $tf = config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A';

        return [
            'available' => $windowAllocations->isEmpty(),
            'name' => $resource ? trim($resource->code.' · '.$resource->name) : null,
            'buffer_minutes' => $buffer,
            'same_day' => $windowStart->isSameDay($windowEnd),
            'window' => [
                'start' => $windowStart->format('Y-m-d H:i'),
                'end' => $windowEnd->format('Y-m-d H:i'),
            ],
            'busy' => collect($this->mergeAllocationIntervals($windowAllocations))->map(function ($iv) use ($tf, $windowStart) {
                $spansDays = ! $iv['start']->isSameDay($iv['end']) || ! $iv['start']->isSameDay($windowStart);

                return [
                    'label' => 'Ocupada',
                    // start/end en 24h para posicionar en la barra visual (mismo día);
                    // text ya formateado para mostrar, con fecha si cruza días.
                    'start' => $iv['start']->format('H:i'),
                    'end' => $iv['end']->format('H:i'),
                    'text' => $spansDays
                        ? $iv['start']->format("d/m $tf").' → '.$iv['end']->format("d/m $tf")
                        : $iv['start']->format($tf).'–'.$iv['end']->format($tf),
                ];
            })->values(),
            // Para la barra visual del día completo — recortado a [dayStart, dayEnd] (una
            // estancia que empieza el día anterior o sigue al siguiente se corta en el borde,
            // igual que `OperatorUnavailability` en `daySummaryFor`).
            'day_busy' => collect($this->mergeAllocationIntervals($dayAllocations))->map(function ($iv) use ($tf, $dayStart, $dayEnd) {
                $start = $iv['start']->lt($dayStart) ? $dayStart : $iv['start'];
                $end = $iv['end']->gt($dayEnd) ? $dayEnd : $iv['end'];

                return [
                    'start' => $start->format('H:i'),
                    'end' => $end->format('H:i'),
                    'text' => $start->format($tf).'–'.$end->format($tf),
                ];
            })->values(),
        ];
    }

    /**
     * Fusiona intervalos contiguos/solapados (ordenados por `starts_at`) en bloques únicos —
     * una reservación real genera 2 filas (uso + limpieza pegada) y puede haber varias
     * asignaciones solapadas; sin esto la agenda mostraría 2-3 bloques ocupados por una sola
     * reservación.
     *
     * @param  Collection<int, ResourceAllocation>  $allocations
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    private function mergeAllocationIntervals(Collection $allocations): array
    {
        $merged = [];
        foreach ($allocations as $a) {
            $last = end($merged);
            if ($last !== false && $a->starts_at->lte($last['end'])) {
                if ($a->ends_at->gt($last['end'])) {
                    $merged[array_key_last($merged)]['end'] = $a->ends_at->copy();
                }
            } else {
                $merged[] = ['start' => $a->starts_at->copy(), 'end' => $a->ends_at->copy()];
            }
        }

        return $merged;
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

        if (! $this->resourceIsAvailable($resource->id, $usageStartsAt->toDateTimeString(), $blockingEndsAt->toDateTimeString())) {
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
