<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'branch_id',
    'resource_type',
    'code',
    'name',
    'capacity_label',
    'administrative_status',
    'operational_status',
    'profile_photo_path',
    'notes',
])]
class Resource extends Model
{
    use SoftDeletes;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ResourcePhoto::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ResourceEvent::class);
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if ($this->profile_photo_path) {
            return Storage::disk('public')->url($this->profile_photo_path);
        }

        return $this->photos()->where('is_primary', true)->first()?->photo_file_url;
    }

    public function getProfilePhotoThumbnailUrlAttribute(): ?string
    {
        if ($this->profile_photo_path) {
            if (str_contains($this->profile_photo_path, '/original/')) {
                return Storage::disk('public')->url(str_replace('/original/', '/thumbs/', $this->profile_photo_path));
            }
            return Storage::disk('public')->url($this->profile_photo_path);
        }

        return $this->photos()->where('is_primary', true)->first()?->photo_thumbnail_url;
    }
}