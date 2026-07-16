<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['spa_booking_id', 'item_id', 'group_id', 'quantity', 'current_price'])]
class SpaBookingItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'current_price' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(SpaBooking::class, 'spa_booking_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
