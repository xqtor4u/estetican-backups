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
        'colonia',
        'city',
        'state',
        'zip',
        'country',
        'lat',
        'lng',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function phones()
    {
        return $this->morphMany(Phone::class, 'phoneable');
    }
}