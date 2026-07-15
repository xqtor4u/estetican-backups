<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'is_active',
    'notes',
])]
class Item extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'department', 'brand', 'presentation', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('catalogo');
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(PetVaccination::class);
    }
}
