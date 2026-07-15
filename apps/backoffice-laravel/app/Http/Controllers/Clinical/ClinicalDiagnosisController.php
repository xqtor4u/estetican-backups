<?php

namespace App\Http\Controllers\Clinical;

use App\Domain\Clinical\Contracts\ClinicalDiagnosisServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\ClinicalDiagnosis;
use App\Models\ClinicalVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClinicalDiagnosisController extends Controller
{
    public function __construct(
        private ClinicalDiagnosisServiceInterface $clinicalDiagnosisService,
    ) {}

    public function store(Request $request, ClinicalVisit $visit): RedirectResponse
    {
        $data = $request->validate([
            'diagnosis' => 'required|string|max:255',
            'diagnosis_type' => 'required|in:presumptive,definitive,differential,ruled_out',
            'icd_code' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $data['clinical_visit_id'] = $visit->id;
        $data['pet_id'] = $visit->pet_id;

        ClinicalDiagnosis::create($data);

        return redirect()->route('clinical.visits.show', $visit)->with('success', 'Diagnóstico agregado.');
    }

    public function promote(ClinicalDiagnosis $diagnosis): RedirectResponse
    {
        $condition = $this->clinicalDiagnosisService->promoteToCondition($diagnosis);

        return redirect()
            ->route('clinical.pets.show', $diagnosis->pet_id)
            ->with('success', "Diagnóstico promovido a condición crónica: {$condition->name}.")
            ->withFragment('conditions');
    }
}
