<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'pet_id',
    'clinical_visit_id',
    'weight_kg',
    'measured_at',
    'recorded_by_operator_id',
    'source',
    'notes',
])]
class PetWeight extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'measured_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pet_id', 'weight_kg', 'measured_at', 'source', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('clinico');
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
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
