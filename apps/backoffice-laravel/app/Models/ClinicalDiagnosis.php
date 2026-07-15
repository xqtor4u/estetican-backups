<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'clinical_visit_id',
    'pet_id',
    'diagnosis',
    'diagnosis_type',
    'icd_code',
    'notes',
    'promoted_to_condition_id',
])]
class ClinicalDiagnosis extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pet_id', 'clinical_visit_id', 'diagnosis', 'diagnosis_type'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('clinico');
    }

    public function clinicalVisit(): BelongsTo
    {
        return $this->belongsTo(ClinicalVisit::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function promotedToCondition(): BelongsTo
    {
        return $this->belongsTo(PetCondition::class, 'promoted_to_condition_id');
    }
}
