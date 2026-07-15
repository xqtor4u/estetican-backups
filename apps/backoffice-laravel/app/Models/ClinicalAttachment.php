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
    'attachment_type',
    'file_path',
    'file_mime_type',
    'description',
    'performed_at',
    'performed_by',
    'uploaded_by_operator_id',
])]
class ClinicalAttachment extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pet_id', 'clinical_visit_id', 'attachment_type', 'description'])
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

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'uploaded_by_operator_id');
    }
}
