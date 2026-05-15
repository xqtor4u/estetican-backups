<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['operator_id', 'operator_role_id', 'proficiency_level', 'is_primary', 'starts_at', 'ends_at'])]
class OperatorRoleAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(OperatorRole::class, 'operator_role_id');
    }
}