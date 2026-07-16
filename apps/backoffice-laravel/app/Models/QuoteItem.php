<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quote_id',
    'service_id',
    'item_id',
    'group_id',
    'quantity',
    'operator_id',
    'is_external',
    'price_override',
    'notes',
])]
class QuoteItem extends Model
{
    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
            'quantity' => 'decimal:2',
            'is_external' => 'boolean',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function name(): string
    {
        return $this->service->name ?? $this->item->name;
    }

    public function unitPrice(): float
    {
        return (float) ($this->price_override ?? $this->service?->price ?? $this->item?->price ?? 0);
    }

    public function lineTotal(): float
    {
        return (float) $this->quantity * $this->unitPrice();
    }
}
