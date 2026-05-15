<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'resource_event_id',
    'resource_event_update_id',
    'photo_url',
    'photo_type',
    'taken_at',
    'description',
    'is_primary',
])]
class ResourceEventPhoto extends Model
{
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ResourceEvent::class, 'resource_event_id');
    }

    public function eventUpdate(): BelongsTo
    {
        return $this->belongsTo(ResourceEventUpdate::class, 'resource_event_update_id');
    }

    public function getPhotoFileUrlAttribute(): string
    {
        if (!$this->photo_url) {
            return '';
        }

        if ($this->storesExternalUrl()) {
            return $this->photo_url;
        }

        return Storage::disk('public')->url($this->photo_url);
    }

    public function getThumbnailStoragePathAttribute(): ?string
    {
        if (!$this->photo_url || $this->storesExternalUrl() || !str_contains($this->photo_url, '/original/')) {
            return null;
        }

        return str_replace('/original/', '/thumbs/', $this->photo_url);
    }

    public function getPhotoThumbnailUrlAttribute(): string
    {
        if ($this->storesExternalUrl()) {
            return $this->photo_url;
        }

        if (!$this->thumbnail_storage_path) {
            return $this->photo_file_url;
        }

        return Storage::disk('public')->url($this->thumbnail_storage_path);
    }

    public function storesExternalUrl(): bool
    {
        return str_starts_with($this->photo_url, 'http://') || str_starts_with($this->photo_url, 'https://');
    }
}