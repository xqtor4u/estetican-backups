<?php

namespace App\Models;

use App\Support\Branches\BranchResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'client_id',
    'branch_id',
    'payable_type',
    'payable_id',
    'document_id',
    'amount',
    'processing_fee',
    'payment_method',
    'destination',
    'external_reference',
    'category',
    'notes',
    'cleared_at',
    'created_by_user_id',
])]
class Payment extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pagos');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'cleared_at' => 'datetime',
        ];
    }

    /** B1: todo pago nuevo nace con su sucursal (la de su cita, o la de quien cobra). */
    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->branch_id ??= BranchResolver::forPayment($payment);
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
