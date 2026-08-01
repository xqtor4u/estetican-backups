<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_date', 'description', 'status',
        'document_id', 'branch_id',
        'created_by_user_id', 'posted_by_user_id', 'posted_at',
        'reference_id', 'reference_type',
        'notes',
        'cancelled_at', 'cancelled_by_user_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    const STATUSES = ['borrador', 'aplicado', 'cancelado'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    // Valida que el asiento esté cuadrado (suma débitos = suma créditos)
    public function isBalanced(): bool
    {
        $totals = $this->lines()
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        return round((float) $totals->total_debit, 2) === round((float) $totals->total_credit, 2);
    }

    public function totalDebit(): float
    {
        return (float) $this->lines()->sum('debit');
    }
}
