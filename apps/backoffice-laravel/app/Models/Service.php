<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'operator_role_id',
    'account_id',
    'type',
    'name',
    'description',
    'price',
    'suggested_price',
    'requires_advance',
    'advance_percentage',
    'duration_minutes',
    'suggested_duration_minutes',
    'lead_time_hours',
    'is_active',
])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'suggested_price' => 'decimal:2',
            'advance_percentage' => 'decimal:2',
            'duration_minutes' => 'integer',
            'suggested_duration_minutes' => 'integer',
            'lead_time_hours' => 'integer',
            'is_active' => 'boolean',
            'requires_advance' => 'boolean',
        ];
    }

    public function executedServiceItems(): HasMany
    {
        return $this->hasMany(ExecutedServiceItem::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function operatorRole(): BelongsTo
    {
        return $this->belongsTo(OperatorRole::class);
    }

    public function spaBookingServices(): HasMany
    {
        return $this->hasMany(SpaBookingService::class);
    }
}
