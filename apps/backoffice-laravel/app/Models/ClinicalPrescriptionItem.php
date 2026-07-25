<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'clinical_prescription_id',
    'item_id',
    'drug_name',
    'concentration',
    'dose',
    'route',
    'frequency',
    'duration_days',
    'special_instructions',
])]
class ClinicalPrescriptionItem extends Model
{
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(ClinicalPrescription::class, 'clinical_prescription_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
