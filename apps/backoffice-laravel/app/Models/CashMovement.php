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
    ];

    protected $casts = [
        'amount' => 'float',
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
}
