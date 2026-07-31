<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['spa_booking_id', 'service_id', 'group_id', 'quantity', 'current_price', 'operator_id', 'is_external', 'external_cost'])]
class SpaBookingService extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'current_price' => 'decimal:2',
            'is_external' => 'boolean',
            'external_cost' => 'decimal:2',
        ];
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
