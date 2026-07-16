<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'group_id',
    'service_id',
    'item_id',
    'quantity',
])]
class GroupComponent extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function name(): string
    {
        return $this->service->name ?? $this->item->name;
    }

    public function unitPrice(): float
    {
        return (float) ($this->service->price ?? $this->item->price ?? 0);
    }
}
