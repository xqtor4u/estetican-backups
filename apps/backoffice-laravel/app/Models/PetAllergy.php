<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'pet_id',
    'allergen',
    'allergen_type',
    'reaction_description',
    'severity',
    'diagnosed_at',
    'is_active',
    'medical_alert_id',
    'clinical_visit_id',
    'recorded_by_operator_id',
])]
class PetAllergy extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'diagnosed_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pet_id', 'allergen', 'allergen_type', 'severity', 'is_active'])
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

    public function clinicalVisit(): BelongsTo
    {
        return $this->belongsTo(ClinicalVisit::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'recorded_by_operator_id');
    }
}
