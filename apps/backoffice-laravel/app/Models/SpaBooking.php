<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

#[Fillable(['pet_id', 'scheduled_at', 'status', 'total_estimated_price', 'notes', 'cancellation_reason'])]
class SpaBooking extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('citas-spa');
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'total_estimated_price' => 'decimal:2',
        ];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(SpaBookingService::class);
    }

    public function executedServices(): HasMany
    {
        return $this->hasMany(ExecutedService::class);
    }

    public function resourceAllocations(): MorphMany
    {
        return $this->morphMany(ResourceAllocation::class, 'source');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
