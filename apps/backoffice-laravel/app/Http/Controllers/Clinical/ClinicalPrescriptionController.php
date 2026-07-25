<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\ClinicalPrescription;
use App\Models\ClinicalVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClinicalPrescriptionController extends Controller
{
    public function store(Request $request, ClinicalVisit $visit): RedirectResponse
    {
        $validated = $request->validate([
            'general_instructions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'nullable|exists:items,id',
            'items.*.drug_name' => 'required|string|max:255',
            'items.*.concentration' => 'nullable|string|max:255',
            'items.*.dose' => 'required|string|max:255',
            'items.*.route' => 'required|in:oral,topical,subcutaneous,intramuscular,intravenous,ophthalmic,otic,other',
            'items.*.frequency' => 'required|string|max:255',
            'items.*.duration_days' => 'nullable|integer|min:1',
            'items.*.special_instructions' => 'nullable|string',
        ]);

        $prescription = ClinicalPrescription::create([
            'clinical_visit_id' => $visit->id,
            'pet_id' => $visit->pet_id,
            'prescribed_by_operator_id' => $visit->operator_id,
            'prescribed_at' => now(),
            'general_instructions' => $validated['general_instructions'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $prescription->items()->create($item);
        }

        return redirect()->route('clinical.visits.show', $visit)->with('success', 'Receta creada.');
    }
}
