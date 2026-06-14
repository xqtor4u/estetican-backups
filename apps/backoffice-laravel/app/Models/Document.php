<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    protected $fillable = [
        'document_series_id', 'document_type', 'folio_number', 'folio_display', 'status',
        'client_id', 'branch_id', 'issued_by_user_id',
        'subtotal', 'tax_amount', 'total',
        'email_to', 'email_sent_at',
        'fiscal_uuid', 'gateway_reference',
        'documentable_id', 'documentable_type',
    ];

    protected $casts = [
        'folio_number'  => 'integer',
        'subtotal'      => 'float',
        'tax_amount'    => 'float',
        'total'         => 'float',
        'email_sent_at' => 'datetime',
    ];

    const STATUSES = ['borrador', 'emitido', 'cancelado'];

    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'document_series_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function journalEntry(): HasOne
    {
        return $this->hasOne(JournalEntry::class);
    }

    public function isCancellable(): bool
    {
        return $this->status === 'emitido';
    }
}
