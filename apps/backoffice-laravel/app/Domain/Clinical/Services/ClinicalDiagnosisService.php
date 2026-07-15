<?php

namespace App\Domain\Clinical\Services;

use App\Domain\Clinical\Contracts\ClinicalDiagnosisServiceInterface;
use App\Models\ClinicalDiagnosis;
use App\Models\PetCondition;
use Illuminate\Support\Facades\DB;

class ClinicalDiagnosisService implements ClinicalDiagnosisServiceInterface
{
    public function promoteToCondition(ClinicalDiagnosis $diagnosis, array $overrides = []): PetCondition
    {
        return DB::transaction(function () use ($diagnosis, $overrides) {
            $condition = PetCondition::create(array_merge([
                'pet_id' => $diagnosis->pet_id,
                'name' => $diagnosis->diagnosis,
                'icd_code' => $diagnosis->icd_code,
                'status' => 'active',
                'onset_date' => $diagnosis->clinicalVisit?->visited_at?->toDateString(),
                'promoted_from_diagnosis_id' => $diagnosis->id,
            ], $overrides));

            $diagnosis->update(['promoted_to_condition_id' => $condition->id]);

            return $condition->fresh();
        });
    }
}
