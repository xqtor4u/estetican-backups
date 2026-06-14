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
            ->get(['id', 'name', 'type', 'price', 'duration_minutes']);

        return response()->json($services->map(fn ($s) => [
            'id'               => $s->id,
            'name'             => $s->name,
            'type'             => $s->type,
            'price'            => (float) $s->price,
            'duration_minutes' => $s->duration_minutes,
        ]));
    }
}
