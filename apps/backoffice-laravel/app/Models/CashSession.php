<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashSession extends Model
{
    protected $fillable = [
        'cash_register_id', 'branch_id',
        'opened_by_user_id', 'opened_at', 'opening_amount',
        'closed_by_user_id', 'closed_at', 'closing_amount',
        'expected_amount', 'difference',
        'status', 'notes',
    ];

    protected $casts = [
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
        'opening_amount'  => 'float',
        'closing_amount'  => 'float',
        'expected_amount' => 'float',
        'difference'      => 'float',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'abierta';
    }
}
