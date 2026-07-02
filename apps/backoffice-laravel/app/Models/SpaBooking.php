<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['pet_id', 'operator_id', 'scheduled_at', 'duration_minutes', 'status', 'total_estimated_price', 'notes', 'cancellation_reason', 'order_series_id', 'order_folio'])]
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

    public function orderSeries(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'order_series_id');
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
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

    public function messages(): HasMany
    {
        return $this->hasMany(BookingMessage::class);
    }
}
