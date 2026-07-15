<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\PetAllergy;
use Illuminate\Http\Request;

class PetAllergyController extends Controller
{
    public function store(Request $request, Pet $pet)
    {
        $pet->allergies()->create($this->validatedData($request));

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Alergia registrada.')
            ->withFragment('allergies');
    }

    public function update(Request $request, Pet $pet, PetAllergy $allergy)
    {
        $allergy->update($this->validatedData($request));

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Alergia actualizada.')
            ->withFragment('allergies');
    }

    public function destroy(Pet $pet, PetAllergy $allergy)
    {
        $allergy->delete();

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Alergia eliminada.')
            ->withFragment('allergies');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'allergen' => 'required|string|max:255',
            'allergen_type' => 'required|in:food,medication,environmental,flea_tick,other',
            'reaction_description' => 'nullable|string',
            'severity' => 'required|in:mild,moderate,severe,anaphylaxis',
            'diagnosed_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }
}
