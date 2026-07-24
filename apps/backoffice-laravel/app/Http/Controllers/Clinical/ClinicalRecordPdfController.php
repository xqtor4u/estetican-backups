<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\ClinicalPrescription;
use App\Models\Pet;
use App\Support\SystemSettings\SystemSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ClinicalRecordPdfController extends Controller
{
    public function __construct(private SystemSettings $settingsService) {}

    public function pet(Pet $pet): Response
    {
        $pet->load([
            'client:id,first_name,apellido_paterno,apellido_materno,email',
            'client.phones',
            'weights' => fn ($q) => $q->orderByDesc('measured_at'),
            'allergies' => fn ($q) => $q->orderByDesc('is_active')->orderByDesc('created_at'),
            'conditions' => fn ($q) => $q->orderBy('status')->orderByDesc('created_at'),
            'vaccinations' => fn ($q) => $q->orderByDesc('expires_at'),
            'attachments' => fn ($q) => $q->orderByDesc('performed_at'),
            'clinicalVisits' => fn ($q) => $q->with(['operator', 'signedBy', 'diagnoses', 'prescriptions.items'])->orderByDesc('visited_at'),
        ]);

        $pdf = Pdf::loadView('clinical.pets.record-pdf', [
            'pet' => $pet,
            'businessName' => $this->businessName(),
            'logoPath' => $this->logoPath(),
        ])->setPaper('letter');

        return $pdf->download("expediente-{$pet->id}-{$pet->name}.pdf");
    }

    public function prescription(ClinicalPrescription $prescription): Response
    {
        $prescription->load([
            'items',
            'prescribedBy',
            'clinicalVisit.pet.client.phones',
        ]);

        $pdf = Pdf::loadView('clinical.prescriptions.pdf', [
            'prescription' => $prescription,
            'businessName' => $this->businessName(),
            'logoPath' => $this->logoPath(),
        ])->setPaper('letter');

        return $pdf->download("receta-{$prescription->id}.pdf");
    }

    private function businessName(): string
    {
        return $this->settingsService->all()['brand_business_name'] ?? 'EstetiCAN';
    }

    private function logoPath(): ?string
    {
        $logo = $this->settingsService->all()['brand_logo_print'] ?? $this->settingsService->all()['brand_logo_web'] ?? null;

        if (! $logo || ! Storage::disk('public')->exists($logo)) {
            return null;
        }

        return Storage::disk('public')->path($logo);
    }
}
