<?php

namespace App\Http\Controllers\Api;

use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ResourceController extends Controller
{
    public function __construct(private ResourceAllocationServiceInterface $resourceAllocationService) {}

    /**
     * Jaulas/recursos activos para el selector del popup de horario de `tstmov` (ZEUS-043).
     * Mismo filtro que ya usa el formulario web de alta de cita (`SpaBookingController`):
     * `resource_type` + `administrative_status` activo/inactivo (nunca `retired`). No se
     * filtra por `operational_status` ni por ocupación — se listan todas y el cliente marca
     * las ocupadas con `availability()` (decisión §0.10 de
     * `SPEC_POPUP_HORARIO_LANDSCAPE_TSTMOV.md`).
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'resource_type' => 'nullable|string|in:cage,room,equipment,other',
        ]);

        $resources = Resource::query()
            ->whereIn('administrative_status', ['active', 'inactive'])
            ->where('resource_type', $validated['resource_type'] ?? 'cage')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'capacity_label', 'operational_status']);

        return response()->json($resources->map(fn ($r) => [
            'id' => $r->id,
            'code' => $r->code,
            'name' => $r->name,
            'label' => trim($r->code.' · '.$r->name),
            'capacity_label' => $r->capacity_label,
            'operational_status' => $r->operational_status,
        ])->values());
    }

    /**
     * Ocupación de una jaula para pintar la barra visual del popup de `tstmov` (ZEUS-043) —
     * mismo resumen que ya usa `SpaBookingController::checkAvailability()` para la web,
     * movido a `ResourceAllocationService::availabilitySummary()` (spec §3.1) para poder
     * compartirlo entre el controlador web y este de API sin duplicar la lógica.
     *
     * `resource_starts_at`/`resource_ends_at` son opcionales — sin ellos, la ventana
     * propuesta cae al día completo de `date` (equivalente a solo pedir `day_busy`, que es lo
     * único que la v1 del popup necesita — la ventana específica del servicio se resuelve del
     * lado del cliente, ver spec §4.2/§0.9).
     */
    public function availability(Request $request, Resource $resource)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'resource_starts_at' => 'nullable|date',
            'resource_ends_at' => 'nullable|date',
            'exclude_booking_id' => 'nullable|integer',
        ]);

        $day = Carbon::parse($validated['date'])->startOfDay();
        $windowStart = ! empty($validated['resource_starts_at']) ? Carbon::parse($validated['resource_starts_at']) : $day->copy();
        $windowEnd = ! empty($validated['resource_ends_at']) ? Carbon::parse($validated['resource_ends_at']) : $day->copy();

        return response()->json($this->resourceAllocationService->availabilitySummary(
            $resource->id,
            $windowStart,
            $windowEnd,
            $validated['exclude_booking_id'] ?? null,
        ));
    }
}
