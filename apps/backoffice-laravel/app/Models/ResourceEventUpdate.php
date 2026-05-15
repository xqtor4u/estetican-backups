<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'resource_event_id',
    'created_by_user_id',
    'update_type',
    'from_status',
    'to_status',
    'notes',
])]
class ResourceEventUpdate extends Model
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(ResourceEvent::class, 'resource_event_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ResourceEventPhoto::class);
    }
}