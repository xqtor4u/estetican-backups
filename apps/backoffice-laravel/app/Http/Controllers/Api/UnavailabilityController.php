<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OperatorUnavailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnavailabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $operator = $request->user()->operator;

        if (! $operator) {
            return response()->json(['message' => 'Tu usuario no tiene un operador vinculado.'], 403);
        }

        return response()->json(
            $operator->unavailabilities()->orderByDesc('starts_at')->get(['id', 'starts_at', 'ends_at', 'reason'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $operator = $request->user()->operator;

        if (! $operator) {
            return response()->json(['message' => 'Tu usuario no tiene un operador vinculado.'], 403);
        }

        $validated = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:255',
        ]);

        $unavailability = $operator->unavailabilities()->create($validated);

        return response()->json($unavailability, 201);
    }

    public function destroy(Request $request, OperatorUnavailability $unavailability): JsonResponse
    {
        $operator = $request->user()->operator;

        abort_unless($operator && $unavailability->operator_id === $operator->id, 403);

        $unavailability->delete();

        return response()->json(['message' => 'Bloqueo eliminado.']);
    }
}
