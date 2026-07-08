<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'lat', 'lng', 'notes', 'is_active'])]
class Vehicle extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lat' => 'decimal:8',
            'lng' => 'decimal:8',
        ];
    }
}
