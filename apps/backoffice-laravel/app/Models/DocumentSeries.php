<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSeries extends Model
{
    protected $fillable = [
        'document_type', 'name', 'prefix', 'suffix',
        'next_number', 'padding', 'branch_id', 'is_active',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'padding'     => 'integer',
        'is_active'   => 'boolean',
    ];

    const TYPES = ['recibo', 'factura', 'sin_documento'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    // Formatea un número según la configuración de la serie
    public function formatFolio(int $number): string
    {
        return $this->prefix . str_pad($number, $this->padding, '0', STR_PAD_LEFT) . $this->suffix;
    }
}
