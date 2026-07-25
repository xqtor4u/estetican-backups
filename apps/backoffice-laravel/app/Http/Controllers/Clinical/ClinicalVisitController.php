<?php

namespace App\Http\Controllers\Clinical;

use App\Domain\Clinical\Contracts\ClinicalVisitServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\ClinicalVisit;
use App\Models\Item;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Support\Pages\ClinicalPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicalVisitController extends Controller
{
    public function __construct(
        private ClinicalVisitServiceInterface $clinicalVisitService,
    ) {}

    public function showPet(Pet $pet): View
    {
        $page = ClinicalPage::showPet($pet);

        $pet->load([
            'client:id,first_name,apellido_paterno,apellido_materno',
            'weights' => fn ($q) => $q->orderByDesc('measured_at')->limit(20),
            'allergies' => fn ($q) => $q->orderByDesc('is_active')->orderByDesc('created_at'),
            'conditions' => fn ($q) => $q->orderBy('status')->orderByDesc('created_at'),
            'vaccinations' => fn ($q) => $q->orderByDesc('expires_at'),
            'clinicalVisits' => fn ($q) => $q->with('operator', 'signedBy')->orderByDesc('visited_at'),
            'attachments' => fn ($q) => $q->orderByDesc('performed_at'),
        ]);

        $operators = Operator::where('is_active', true)->orderBy('name')->get(['id', 'name', 'operator_role_id']);
        $vaccineServices = Service::where('type', 'vaccine')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $vaccineItems = Item::where('is_active', true)->where('department', 'Vacunas')->orderBy('name')->get(['id', 'name', 'brand', 'presentation']);

        return view('clinical.pets.show', compact('page', 'pet', 'operators', 'vaccineServices', 'vaccineItems'));
    }

    public function create(Pet $pet): View
    {
        $page = ClinicalPage::createVisit($pet);
        $operators = Operator::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('clinical.visits.create', compact('page', 'pet', 'operators'));
    }

    public function store(Request $request, Pet $pet): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['pet_id'] = $pet->id;

        $visit = $this->clinicalVisitService->createDraft($data);

        return redirect()
            ->route('clinical.visits.show', $visit)
            ->with('success', 'Visita clínica creada en borrador.');
    }

    public function show(ClinicalVisit $visit): View
    {
        $page = ClinicalPage::showVisit($visit);

        $visit->load(['pet', 'operator', 'signedBy', 'diagnoses', 'prescriptions.items', 'amendsVisit', 'amendments']);

        $pharmacyItems = Item::where('is_active', true)->where('department', 'Farmacia')->orderBy('name')->get(['id', 'name', 'brand', 'presentation']);

        return view('clinical.visits.show', compact('page', 'visit', 'pharmacyItems'));
    }

    public function edit(ClinicalVisit $visit): View
    {
        $page = ClinicalPage::showVisit($visit);
        $operators = Operator::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('clinical.visits.edit', compact('page', 'visit', 'operators'));
    }

    public function update(Request $request, ClinicalVisit $visit): RedirectResponse
    {
        $data = $this->validatedData($request);

        $this->clinicalVisitService->updateDraft($visit, $data);

        return redirect()
            ->route('clinical.visits.show', $visit)
            ->with('success', 'Visita actualizada.');
    }

    public function sign(Request $request, ClinicalVisit $visit): RedirectResponse
    {
        $this->clinicalVisitService->sign($visit, $request->user());

        return redirect()
            ->route('clinical.visits.show', $visit)
            ->with('success', 'Visita firmada — ya es inmutable.');
    }

    public function createAmendment(ClinicalVisit $visit): View
    {
        $page = ClinicalPage::showVisit($visit);
        $operators = Operator::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('clinical.visits.amend', compact('page', 'visit', 'operators'));
    }

    public function storeAmendment(Request $request, ClinicalVisit $visit): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['amendment_reason'] = $request->validate(['amendment_reason' => 'required|string'])['amendment_reason'];

        $amendment = $this->clinicalVisitService->createAmendment($visit, $data);

        return redirect()
            ->route('clinical.visits.show', $amendment)
            ->with('success', 'Nota aclaratoria creada.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'operator_id' => 'required|exists:operators,id',
            'branch_id' => 'nullable|exists:branches,id',
            'visit_type' => 'required|in:consultation,follow_up,emergency,pre_grooming_check,vaccination',
            'visited_at' => 'required|date',
            'reason_for_visit' => 'required|string',
            'subjective' => 'nullable|string',
            'weight_kg' => 'nullable|numeric|min:0|max:999.99',
            'temperature_celsius' => 'nullable|numeric|min:0|max:99.9',
            'heart_rate_bpm' => 'nullable|integer|min:0',
            'respiratory_rate_bpm' => 'nullable|integer|min:0',
            'mucous_membranes' => 'nullable|in:pink,pale,cyanotic,icteric,congested',
            'hydration_status' => 'nullable|in:normal,mild_dehydration,moderate_dehydration,severe_dehydration',
            'body_condition_score' => 'nullable|integer|min:1|max:9',
            'objective_notes' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'follow_up_at' => 'nullable|date',
            'is_external' => 'nullable|boolean',
            'external_provider_name' => 'nullable|string|max:255',
            'external_provider_license' => 'nullable|string|max:255',
            'external_clinic_name' => 'nullable|string|max:255',
            'external_status' => 'nullable|in:pending_external_report,completed',
        ]);
    }
}
