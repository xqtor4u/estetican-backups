<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'resource_id',
    'client_id',
    'pet_id',
    'service_id',
    'detected_by_user_id',
    'responsible_user_id',
    'closed_by_user_id',
    'source_type',
    'source_id',
    'event_type',
    'event_status',
    'severity',
    'title',
    'description',
    'occurred_at',
    'detected_at',
    'resolved_at',
])]
class ResourceEvent extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function detectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detected_by_user_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ResourceEventUpdate::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ResourceEventPhoto::class);
    }
}