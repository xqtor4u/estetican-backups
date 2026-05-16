<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'type',
        'street',
        'exterior_number',
        'interior_number',
        'colonia',
        'city',
        'state',
        'zip',
        'country',
        'lat',
        'lng',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
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
}