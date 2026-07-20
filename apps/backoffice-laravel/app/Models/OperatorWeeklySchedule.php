<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operator_id',
    'day_of_week',
    'start_time',
    'end_time',
])]
class OperatorWeeklySchedule extends Model
{
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
