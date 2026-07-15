<?php

namespace App\Models;

use Database\Factories\OperatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

#[Fillable([
    'code',
    'first_name',
    'apellido_paterno',
    'apellido_materno',
    'name',
    'operator_role_id',
    'ine_number',
    'imss_number',
    'address',
    'phone',
    'profile_photo_path',
    'professional_license',
    'specialty',
    'university',
    'emergency_contact_name',
    'emergency_contact_phone',
    'hire_date',
    'role',
    'is_active',
    'notes',
])]
class Operator extends Model
{
    /** @use HasFactory<OperatorFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hire_date' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return implode(' ', array_filter([
            $this->first_name,
            $this->apellido_paterno,
            $this->apellido_materno,
        ], fn ($part) => filled($part)));
    }

    public function operatorRole(): BelongsTo
    {
        return $this->belongsTo(OperatorRole::class, 'operator_role_id');
    }

    public function executedServices(): HasMany
    {
        return $this->hasMany(ExecutedService::class);
    }

    public function clinicalVisitsAttended(): HasMany
    {
        return $this->hasMany(ClinicalVisit::class, 'operator_id');
    }

    public function clinicalVisitsSigned(): HasMany
    {
        return $this->hasMany(ClinicalVisit::class, 'signed_by_operator_id');
    }

    public function isVeterinario(): bool
    {
        return $this->operatorRole?->code === 'veterinario';
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(OperatorRoleAssignment::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(OperatorRole::class, 'operator_role_assignments')
            ->withPivot(['proficiency_level', 'is_primary', 'starts_at', 'ends_at'])
            ->withTimestamps();
    }

    public function branchAssignments(): HasMany
    {
        return $this->hasMany(OperatorBranchAssignment::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'operator_branch_assignments')
            ->withPivot(['is_primary', 'starts_at', 'ends_at'])
            ->withTimestamps();
    }

    public function compensationProfiles(): HasMany
    {
        return $this->hasMany(OperatorCompensationProfile::class);
    }

    public function activeRoles(): Collection
    {
        return $this->roles
            ->filter(fn (OperatorRole $role) => !$role->pivot?->ends_at)
            ->values();
    }

    public function primaryBranch(): ?Branch
    {
        return $this->branches->firstWhere('pivot.is_primary', true)
            ?? $this->branches->first();
    }

    public function currentCompensationProfile(): ?OperatorCompensationProfile
    {
        return $this->compensationProfiles
            ->filter(fn (OperatorCompensationProfile $profile) => !$profile->effective_to)
            ->sortByDesc('effective_from')
            ->first();
    }

    public function effectiveHourlyRate(): ?float
    {
        $currentProfile = $this->currentCompensationProfile();

        if ($currentProfile?->hourly_rate !== null) {
            return (float) $currentProfile->hourly_rate;
        }

        return $this->activeRoles()
            ->sortByDesc(fn (OperatorRole $role) => (bool) $role->pivot?->is_primary)
            ->pluck('default_hourly_rate')
            ->filter(fn ($rate) => $rate !== null)
            ->map(fn ($rate) => (float) $rate)
            ->first();
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        if (!$this->profile_photo_path) {
            return '';
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }

    public function getProfilePhotoThumbnailPathAttribute(): ?string
    {
        if (!$this->profile_photo_path || !str_contains($this->profile_photo_path, '/original/')) {
            return null;
        }

        return str_replace('/original/', '/thumbs/', $this->profile_photo_path);
    }

    public function getProfilePhotoThumbnailUrlAttribute(): string
    {
        if (!$this->profile_photo_path) {
            return '';
        }

        if (!$this->profile_photo_thumbnail_path) {
            return $this->profile_photo_url;
        }

        return Storage::disk('public')->url($this->profile_photo_thumbnail_path);
    }
}