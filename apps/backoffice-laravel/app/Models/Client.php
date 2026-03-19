<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'first_name',
        'last_name',
        'email',
        'address',
        'city',
        'state',
        'zip_code',
        'notes',
    ];

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function phones()
    {
        return $this->morphMany(Phone::class, 'phoneable');
    }
}
