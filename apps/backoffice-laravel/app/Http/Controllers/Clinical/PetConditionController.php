<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\PetCondition;
use Illuminate\Http\Request;

class PetConditionController extends Controller
{
    public function store(Request $request, Pet $pet)
    {
        $pet->conditions()->create($this->validatedData($request));

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Condición registrada.')
            ->withFragment('conditions');
    }

    public function update(Request $request, Pet $pet, PetCondition $condition)
    {
        $condition->update($this->validatedData($request));

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Condición actualizada.')
            ->withFragment('conditions');
    }

    public function destroy(Pet $pet, PetCondition $condition)
    {
        $condition->delete();

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Condición eliminada.')
            ->withFragment('conditions');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'icd_code' => 'nullable|string|max:255',
            'status' => 'required|in:active,controlled,resolved,chronic_monitoring',
            'onset_date' => 'nullable|date',
            'resolved_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
    }
}
