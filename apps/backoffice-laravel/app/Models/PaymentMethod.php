<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'account_id', 'gateway_config',
        'requires_reference', 'is_active',
    ];

    protected $casts = [
        'gateway_config'     => 'array',
        'requires_reference' => 'boolean',
        'is_active'          => 'boolean',
    ];

    // Tipos válidos
    const TYPES = ['cash', 'card', 'transfer', 'crypto', 'gateway'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
