<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'street', 'exterior_number', 'interior_number', 'colonia', 'city', 'state', 'zip', 'country', 'lat', 'lng', 'is_active', 'notes'])]
class Branch extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lat' => 'decimal:8',
            'lng' => 'decimal:8',
        ];
    }

    public function operatorAssignments(): HasMany
    {
        return $this->hasMany(OperatorBranchAssignment::class);
    }

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(Operator::class, 'operator_branch_assignments')
            ->withPivot(['is_primary', 'starts_at', 'ends_at'])
            ->withTimestamps();
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function getFormattedAddressAttribute(): string
    {
        $streetLine = trim(implode(' ', array_filter([
            $this->street,
            $this->exterior_number,
        ], fn ($value) => is_string($value) && trim($value) !== '')));

        if ($this->interior_number && trim($this->interior_number) !== '') {
            $streetLine = trim($streetLine . ' Int ' . trim($this->interior_number));
        }

        $parts = array_filter([
            $streetLine,
            $this->colonia,
            $this->city,
            $this->state,
            $this->zip,
            $this->country,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        return implode(', ', $parts);
    }

    public function getGoogleMapsUrlAttribute(): string
    {
        if ($this->lat !== null && $this->lng !== null) {
            return 'https://www.google.com/maps?q=' . $this->lat . ',' . $this->lng;
        }

        if ($this->formatted_address === '') {
            return '';
        }

        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($this->formatted_address);
    }

    public function getWhatsAppAddressTextAttribute(): string
    {
        $lines = array_filter([
            'Sucursal: ' . $this->name,
            $this->formatted_address !== '' ? 'Dirección: ' . $this->formatted_address : null,
            $this->google_maps_url !== '' ? 'Maps: ' . $this->google_maps_url : null,
        ]);

        return implode("\n", $lines);
    }

    public function getWhatsAppShareUrlAttribute(): string
    {
        if ($this->whats_app_address_text === '') {
            return '';
        }

        return 'https://wa.me/?text=' . rawurlencode($this->whats_app_address_text);
    }
}