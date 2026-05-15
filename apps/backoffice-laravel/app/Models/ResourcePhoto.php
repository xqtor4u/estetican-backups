<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ResourcePhoto extends Model
{
    protected $fillable = [
        'resource_id',
        'photo_url',
        'photo_type',
        'taken_at',
        'description',
        'is_primary',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
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