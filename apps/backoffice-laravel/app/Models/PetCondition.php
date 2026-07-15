<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'pet_id',
    'name',
    'icd_code',
    'status',
    'onset_date',
    'resolved_date',
    'promoted_from_diagnosis_id',
    'notes',
    'medical_alert_id',
])]
class PetCondition extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'onset_date' => 'date',
            'resolved_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pet_id', 'name', 'status', 'onset_date', 'resolved_date'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('clinico');
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function medicalAlert(): BelongsTo
    {
        return $this->belongsTo(PetMedicalAlert::class, 'medical_alert_id');
    }

    public function promotedFromDiagnosis(): BelongsTo
    {
        return $this->belongsTo(ClinicalDiagnosis::class, 'promoted_from_diagnosis_id');
    }

    public function promotedDiagnoses(): HasMany
    {
        return $this->hasMany(ClinicalDiagnosis::class, 'promoted_to_condition_id');
    }
}
