<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'resource_id',
    'parent_allocation_id',
    'pet_id',
    'allocation_type',
    'starts_at',
    'ends_at',
    'source_type',
    'source_id',
    'notes',
])]
class ResourceAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function parentAllocation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_allocation_id');
    }

    public function childAllocations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_allocation_id');
    }

    public function scopeOverlapping(Builder $query, Carbon $startsAt, Carbon $endsAt): Builder
    {
        return $query
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }
}