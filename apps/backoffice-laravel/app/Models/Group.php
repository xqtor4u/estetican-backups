<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'description',
    'is_active',
    'notes',
])]
class Group extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function components(): HasMany
    {
        return $this->hasMany(GroupComponent::class);
    }

    /**
     * Precio calculado al vuelo (SUM de componentes × precio vigente del catálogo) —
     * sin caché, para que un cambio de precio en Service/Item se refleje sin invalidar nada.
     */
    public function calculatedPrice(): float
    {
        return (float) $this->components->sum(
            fn (GroupComponent $component) => (float) $component->quantity * $component->unitPrice()
        );
    }
}
