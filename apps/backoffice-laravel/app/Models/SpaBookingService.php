<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['spa_booking_id', 'service_id', 'group_id', 'quantity', 'current_price', 'started_at', 'completed_at', 'cancelled_at', 'cancellation_reason', 'not_performed_at', 'not_performed_reason', 'operator_id', 'is_external', 'external_cost'])]
class SpaBookingService extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'current_price' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'not_performed_at' => 'datetime',
            'is_external' => 'boolean',
            'external_cost' => 'decimal:2',
        ];
    }

    /** Líneas que sí se cobran: ni canceladas ni marcadas como no realizadas. */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at')->whereNull('not_performed_at');
    }

    /** Estado efectivo de la línea, en cascada de prioridad. */
    public function getLineStateAttribute(): string
    {
        return match (true) {
            $this->cancelled_at !== null => 'cancelled',
            $this->not_performed_at !== null => 'not_performed',
            $this->completed_at !== null => 'completed',
            $this->started_at !== null => 'in_progress',
            default => 'pending',
        };
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(SpaBooking::class, 'spa_booking_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
