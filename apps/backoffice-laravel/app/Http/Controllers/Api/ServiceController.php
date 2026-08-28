<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'price', 'duration_minutes', 'operator_role_id']);

        return response()->json($services->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'type' => $s->type,
            'price' => (float) $s->price,
            'duration_minutes' => $s->duration_minutes,
            // Rol que se necesita para ejecutar el servicio (null = cualquier operador).
            // El agendado móvil filtra los operadores ofrecidos por línea con esto.
            'operator_role_id' => $s->operator_role_id,
        ]));
    }
}
