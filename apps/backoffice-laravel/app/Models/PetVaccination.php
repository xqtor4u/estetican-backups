<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'pet_id',
    'service_id',
    'item_id',
    'is_external',
    'vaccine_name',
    'applied_at',
    'expires_at',
    'notes',
    'lot_number',
    'manufacturer',
    'administered_by_operator_id',
    'clinical_visit_id',
    'dose_number',
    'route',
    'site',
    'reaction_notes',
])]
class PetVaccination extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'applied_at' => 'date',
            'expires_at' => 'date',
            'dose_number' => 'integer',
            'is_external' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pet_id', 'service_id', 'item_id', 'is_external', 'vaccine_name', 'applied_at', 'expires_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('clinico');
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'administered_by_operator_id');
    }

    public function clinicalVisit(): BelongsTo
    {
        return $this->belongsTo(ClinicalVisit::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
