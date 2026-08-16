<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $fillable = [
        'cash_session_id',
        'type',
        'direction',
        'amount',
        'concept',
        'notes',
        'counterpart_account_id',
        'journal_entry_id',
        'created_by_user_id',
        'reversed_at',
        'reversal_of_movement_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'reversed_at' => 'datetime',
    ];

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function counterpartAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counterpart_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** El movimiento original que este revierte (si este mismo ES una reversión). */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class, 'reversal_of_movement_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
