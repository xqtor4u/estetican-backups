<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\ClinicalAttachment;
use App\Models\Pet;
use App\Support\ClinicalAttachmentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClinicalAttachmentController extends Controller
{
    public function __construct(private readonly ClinicalAttachmentManager $attachmentManager) {}

    public function store(Request $request, Pet $pet): RedirectResponse
    {
        $validated = $request->validate([
            'attachment_type' => 'required|in:lab_result,xray,ultrasound,other_imaging,referral_letter,other',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:15360',
            'description' => 'nullable|string',
            'performed_at' => 'nullable|date',
            'performed_by' => 'nullable|string|max:255',
            'clinical_visit_id' => [
                'nullable',
                Rule::exists('clinical_visits', 'id')->where('pet_id', $pet->id),
            ],
        ]);

        $stored = $this->attachmentManager->store($request->file('file'));

        $pet->attachments()->create([
            'clinical_visit_id' => $validated['clinical_visit_id'] ?? null,
            'attachment_type' => $validated['attachment_type'],
            'file_path' => $stored['file_path'],
            'file_mime_type' => $stored['file_mime_type'],
            'description' => $validated['description'] ?? null,
            'performed_at' => $validated['performed_at'] ?? null,
            'performed_by' => $validated['performed_by'] ?? null,
            'uploaded_by_operator_id' => $request->user()->operator?->id,
        ]);

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Adjunto agregado.')
            ->withFragment('attachments');
    }

    public function destroy(Pet $pet, ClinicalAttachment $attachment): RedirectResponse
    {
        abort_if($attachment->pet_id !== $pet->id, 404);

        $this->attachmentManager->delete($attachment->file_path);
        $attachment->delete();

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Adjunto eliminado.')
            ->withFragment('attachments');
    }
}
