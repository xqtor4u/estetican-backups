<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo de stock cacheado por (item, branch) — ver ItemMovementService::record().
 * No es un ledger: se recalcula por completo desde item_movements, nunca se edita a mano.
 */
#[Fillable(['item_id', 'branch_id', 'quantity'])]
class ItemBranchStock extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
