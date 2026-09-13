<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'client_id',
    'payable_type',
    'payable_id',
    'document_id',
    'amount',
    'payment_method',
    'external_reference',
    'processing_fee',
    'category',
    'notes',
    'cleared_at',
    'created_by_user_id',
])]
/**
 * @deprecated SYNC-098 — `payments` es la tabla única canónica de cobro (móvil y web).
 * Ya nada escribe aquí; el modelo se conserva solo como cascarón hasta que el porteo a
 * producción haga el backfill de datos históricos y el DROP de la tabla. No usar en código nuevo.
 */
class BankLedger extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'cleared_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
