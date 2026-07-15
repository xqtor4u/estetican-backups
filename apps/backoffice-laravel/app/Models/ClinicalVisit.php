<?php

namespace App\Models;

use App\Domain\Clinical\Exceptions\ClinicalVisitLockedException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'pet_id',
    'operator_id',
    'branch_id',
    'visit_type',
    'visited_at',
    'reason_for_visit',
    'subjective',
    'weight_kg',
    'temperature_celsius',
    'heart_rate_bpm',
    'respiratory_rate_bpm',
    'mucous_membranes',
    'hydration_status',
    'body_condition_score',
    'objective_notes',
    'assessment',
    'plan',
    'follow_up_at',
    'amends_visit_id',
    'amendment_reason',
    'is_external',
    'external_provider_name',
    'external_provider_license',
    'external_clinic_name',
    'external_status',
    'status',
    'signed_by_operator_id',
    'signed_at',
    'professional_license_snapshot',
])]
class ClinicalVisit extends Model
{
    use LogsActivity;

    /**
     * Bandera interna, no persistida — permite que ClinicalVisitService marque la visita
     * original como 'amended' (solo ese campo) sin disparar el guard de inmutabilidad.
     */
    public bool $allowLockedStatusTransition = false;

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'temperature_celsius' => 'decimal:1',
            'heart_rate_bpm' => 'integer',
            'respiratory_rate_bpm' => 'integer',
            'body_condition_score' => 'integer',
            'follow_up_at' => 'date',
            'signed_at' => 'datetime',
            'is_external' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pet_id', 'operator_id', 'visit_type', 'visited_at', 'status', 'signed_by_operator_id', 'signed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('clinico');
    }

    protected static function booted(): void
    {
        static::saving(function (ClinicalVisit $visit) {
            if (! $visit->exists) {
                return;
            }

            $originalStatus = $visit->getOriginal('status');

            if (in_array($originalStatus, ['signed', 'amended'], true) && ! $visit->allowLockedStatusTransition) {
                throw ClinicalVisitLockedException::forVisit($visit->id);
            }
        });
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'signed_by_operator_id');
    }

    public function amendsVisit(): BelongsTo
    {
        return $this->belongsTo(ClinicalVisit::class, 'amends_visit_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ClinicalVisit::class, 'amends_visit_id');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(ClinicalDiagnosis::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(ClinicalPrescription::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClinicalAttachment::class);
    }

    public function weights(): HasMany
    {
        return $this->hasMany(PetWeight::class);
    }
}
