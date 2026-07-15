<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'clinical_visit_id',
    'pet_id',
    'prescribed_by_operator_id',
    'prescribed_at',
    'general_instructions',
])]
class ClinicalPrescription extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'prescribed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pet_id', 'clinical_visit_id', 'prescribed_by_operator_id', 'prescribed_at'])
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

    public function prescribedBy(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'prescribed_by_operator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClinicalPrescriptionItem::class);
    }
}
