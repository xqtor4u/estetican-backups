<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Maestro de artículos — fundación atómica para el futuro módulo de Tienda/Inventario (BL-049).
 * Deliberadamente sin existencia/stock: solo identidad del producto (nombre, marca, presentación,
 * departamento). CRUD propio en Catálogos; primer consumidor real: pet_vaccinations (BL-048/050).
 */
#[Fillable([
    'name',
    'department',
    'brand',
    'presentation',
    'price',
    'is_active',
    'ai_visible',
    'stock_quantity',
    'account_id',
    'photo_path',
    'notes',
])]
class Item extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'ai_visible' => 'boolean',
            'stock_quantity' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'department', 'brand', 'presentation', 'price', 'is_active', 'ai_visible', 'stock_quantity'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('catalogo');
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(PetVaccination::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ItemMovement::class);
    }

    public function getPhotoUrlAttribute(): string
    {
        if (!$this->photo_path) {
            return '';
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    public function getPhotoThumbnailPathAttribute(): ?string
    {
        if (!$this->photo_path || !str_contains($this->photo_path, '/original/')) {
            return null;
        }

        return str_replace('/original/', '/thumbs/', $this->photo_path);
    }

    public function getPhotoThumbnailUrlAttribute(): string
    {
        if (!$this->photo_path) {
            return '';
        }

        if (!$this->photo_thumbnail_path) {
            return $this->photo_url;
        }

        return Storage::disk('public')->url($this->photo_thumbnail_path);
    }
}
