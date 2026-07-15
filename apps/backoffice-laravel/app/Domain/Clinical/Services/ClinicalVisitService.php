<?php

namespace App\Domain\Clinical\Services;

use App\Domain\Clinical\Contracts\ClinicalVisitServiceInterface;
use App\Domain\Clinical\Exceptions\ClinicalVisitLockedException;
use App\Domain\Clinical\Exceptions\UnauthorizedClinicalSignatureException;
use App\Models\ClinicalVisit;
use App\Models\PetWeight;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClinicalVisitService implements ClinicalVisitServiceInterface
{
    public function createDraft(array $data): ClinicalVisit
    {
        return DB::transaction(function () use ($data) {
            $visit = ClinicalVisit::create($data);

            $this->mirrorWeight($visit);

            return $visit->fresh();
        });
    }

    public function updateDraft(ClinicalVisit $visit, array $data): ClinicalVisit
    {
        if ($visit->status !== 'draft') {
            throw ClinicalVisitLockedException::forVisit($visit->id);
        }

        return DB::transaction(function () use ($visit, $data) {
            $visit->update($data);

            $this->mirrorWeight($visit);

            return $visit->fresh();
        });
    }

    public function sign(ClinicalVisit $visit, User $signer): ClinicalVisit
    {
        if ($visit->is_external) {
            throw UnauthorizedClinicalSignatureException::isExternal();
        }

        if ($visit->status !== 'draft') {
            throw UnauthorizedClinicalSignatureException::notInDraft();
        }

        if (! $signer->can('clinico.firmar')) {
            throw UnauthorizedClinicalSignatureException::missingPermission();
        }

        $operator = $signer->operator;

        if (! $operator || ! $operator->isVeterinario()) {
            throw UnauthorizedClinicalSignatureException::notVeterinarian();
        }

        if (! $operator->professional_license) {
            throw UnauthorizedClinicalSignatureException::missingProfessionalLicense();
        }

        $visit->update([
            'signed_by_operator_id' => $operator->id,
            'signed_at' => now(),
            'professional_license_snapshot' => $operator->professional_license,
            'status' => 'signed',
        ]);

        return $visit->fresh();
    }

    public function createAmendment(ClinicalVisit $original, array $data): ClinicalVisit
    {
        if ($original->status !== 'signed') {
            throw UnauthorizedClinicalSignatureException::originalNotSigned();
        }

        return DB::transaction(function () use ($original, $data) {
            $amendment = ClinicalVisit::create(array_merge($data, [
                'pet_id' => $original->pet_id,
                'amends_visit_id' => $original->id,
            ]));

            $original->allowLockedStatusTransition = true;
            $original->status = 'amended';
            $original->save();
            $original->allowLockedStatusTransition = false;

            $this->mirrorWeight($amendment);

            return $amendment->fresh();
        });
    }

    private function mirrorWeight(ClinicalVisit $visit): void
    {
        if ($visit->weight_kg === null) {
            return;
        }

        PetWeight::updateOrCreate(
            ['clinical_visit_id' => $visit->id],
            [
                'pet_id' => $visit->pet_id,
                'weight_kg' => $visit->weight_kg,
                'measured_at' => $visit->visited_at,
                'recorded_by_operator_id' => $visit->operator_id,
                'source' => 'clinical_visit',
            ]
        );
    }
}
