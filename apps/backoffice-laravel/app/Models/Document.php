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
        'subtotal', 'tax_amount', 'total', 'line_items_snapshot',
        'email_to', 'email_sent_at',
        'fiscal_uuid', 'gateway_reference',
        'documentable_id', 'documentable_type',
        'cancelled_at', 'cancelled_by_user_id', 'cancellation_type', 'cancellation_reason',
        'supersedes_document_id',
    ];

    protected $casts = [
        'folio_number' => 'integer',
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'total' => 'float',
        'email_sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'line_items_snapshot' => 'array',
    ];

    const STATUSES = ['borrador', 'emitido', 'cancelado'];

    // El dinero se queda donde está — solo se corrige el papel (nombre, servicio, descripción).
    const CANCELLATION_TYPE_CORRECTION = 'correction';

    // Reembolso real — implica una reversión de dinero en CashLedger/BankLedger.
    const CANCELLATION_TYPE_REFUND = 'refund';

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

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** El documento que este reemplaza (si es una reemisión). */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'supersedes_document_id');
    }

    /** El documento que reemplazó a este (si fue cancelado y reemitido). */
    public function replacement(): HasOne
    {
        return $this->hasOne(Document::class, 'supersedes_document_id');
    }

    public function isCancellable(): bool
    {
        return $this->status === 'emitido';
    }
}
