<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'executed_service_id',
    'service_id',
    'service_name_snapshot',
    'service_description_snapshot',
    'service_type_snapshot',
    'charged_price',
    'duration_minutes_snapshot',
    'operator_id',
    'is_external',
])]
class ExecutedServiceItem extends Model
{
    protected function casts(): array
    {
        return [
            'charged_price' => 'decimal:2',
            'duration_minutes_snapshot' => 'integer',
            'is_external' => 'boolean',
        ];
    }

    public function executedService(): BelongsTo
    {
        return $this->belongsTo(ExecutedService::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
