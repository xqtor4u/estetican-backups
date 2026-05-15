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
        return $this->hasMany(Phone::class);
    }

    public function pets()
    {
        return $this->hasMany(Pet::class);
    }

    public function primaryPetPhotos()
    {
        return $this->hasManyThrough(PetPhoto::class, Pet::class)->where('pet_photos.is_primary', true);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function cashLedgers()
    {
        return $this->hasMany(CashLedger::class);
    }

    public function bankLedgers()
    {
        return $this->hasMany(BankLedger::class);
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
