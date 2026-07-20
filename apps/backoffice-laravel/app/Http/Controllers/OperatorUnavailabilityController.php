<?php

namespace App\Http\Controllers;

use App\Models\Operator;
use App\Models\OperatorUnavailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OperatorUnavailabilityController extends Controller
{
    public function store(Request $request, Operator $operator): RedirectResponse
    {
        $validated = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:255',
        ]);

        $operator->unavailabilities()->create($validated);

        return redirect()->route('operators.edit', $operator)->with('success', 'Bloqueo de no disponibilidad registrado.');
    }

    public function destroy(Operator $operator, OperatorUnavailability $unavailability): RedirectResponse
    {
        abort_unless($unavailability->operator_id === $operator->id, 404);

        $unavailability->delete();

        return redirect()->route('operators.edit', $operator)->with('success', 'Bloqueo de no disponibilidad eliminado.');
    }
}
