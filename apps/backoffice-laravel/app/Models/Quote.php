<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'spa_booking_id',
    'version_label',
    'status',
    'total_amount',
    'advance_amount',
    'advance_payment_method',
    'notes',
])]
class Quote extends Model
{
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
        ];
    }

    public function spaBooking(): BelongsTo
    {
        return $this->belongsTo(SpaBooking::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function cashLedgers(): MorphMany
    {
        return $this->morphMany(CashLedger::class, 'payable');
    }

    public function bankLedgers(): MorphMany
    {
        return $this->morphMany(BankLedger::class, 'payable');
    }
}
