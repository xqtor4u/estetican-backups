<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['executed_service_id', 'stay_id', 'photo_url', 'stage', 'description'])]
class ServicePhoto extends Model
{
    public function executedService(): BelongsTo
    {
        return $this->belongsTo(ExecutedService::class);
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }
}
