<?php

namespace App\Models;

use App\Support\CatalogCache\OperatorRoleCatalogCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'acronym', 'name', 'description', 'notes', 'default_hourly_rate', 'is_active', 'can_login'])]
class OperatorRole extends Model
{
    protected static function booted(): void
    {
        static::saved(static function (): void {
            OperatorRoleCatalogCache::flush();
        });

        static::deleted(static function (): void {
            OperatorRoleCatalogCache::flush();
        });
    }

    protected function casts(): array
    {
        return [
            'default_hourly_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'can_login' => 'boolean',
        ];
    }

    /** Devuelve el acrónimo si existe, o los primeros 3 chars del code como fallback. */
    public function getShortLabelAttribute(): string
    {
        return $this->acronym ?? strtoupper(substr($this->code, 0, 3));
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OperatorRoleAssignment::class);
    }

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(Operator::class, 'operator_role_assignments')
            ->withPivot(['proficiency_level', 'is_primary', 'starts_at', 'ends_at'])
            ->withTimestamps();
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}