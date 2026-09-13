<?php

namespace App\Http\Controllers\Api;

use App\Domain\Planning\Services\OperatorServiceResolver;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function __construct(private OperatorServiceResolver $operatorServiceResolver) {}

    public function index()
    {
        $services = Service::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'price', 'duration_minutes', 'operator_role_id', 'open_to_all_operators']);

        return response()->json($services->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'type' => $s->type,
            'price' => (float) $s->price,
            'duration_minutes' => $s->duration_minutes,
            // `operator_role_id` queda obsoleto desde SYNC-073 (Fase 1) — la fuente de verdad
            // de quién puede hacer el servicio es GET /api/services/{service}/operators. Se
            // conserva aquí hasta que se porte la Fase 3 (que lo elimina de verdad).
            'operator_role_id' => $s->operator_role_id,
            'open_to_all_operators' => (bool) $s->open_to_all_operators,
        ]));
    }

    /**
     * Operadores (activos) que pueden realizar este servicio — plantilla del rol de puesto ∪
     * capacidades directas grant − revoke, o todos si `open_to_all_operators` (SYNC-073).
     * El agendado (móvil y web) pide esta lista por línea de servicio.
     */
    public function operators(Service $service)
    {
        $operators = $this->operatorServiceResolver->operatorsFor($service);

        return response()->json($operators->map(fn ($o) => [
            'id' => $o->id,
            'name' => $o->full_name,
            'photo_url' => $o->profile_photo_path
                ? Storage::disk('public')->url($o->profile_photo_path)
                : null,
        ])->values());
    }
}
