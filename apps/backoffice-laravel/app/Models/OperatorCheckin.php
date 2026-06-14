<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'branch_id', 'checked_in_at', 'checked_out_at', 'auto_checkout', 'transgression_note'])]
class OperatorCheckin extends Model
{
    protected function casts(): array
    {
        return [
            'checked_in_at'  => 'datetime',
            'checked_out_at' => 'datetime',
            'auto_checkout'  => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
