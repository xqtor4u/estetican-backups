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
        'apellido_paterno',
        'apellido_materno',
        'email',
        'address',
        'city',
        'state',
        'zip_code',
        'notes',
        'receives_offers',
        'receives_service_reminders',
        'receives_job_updates',
        'receives_account_statements',
        'receives_other_notifications',
    ];

    protected function casts(): array
    {
        return [
            'receives_offers' => 'boolean',
            'receives_service_reminders' => 'boolean',
            'receives_job_updates' => 'boolean',
            'receives_account_statements' => 'boolean',
            'receives_other_notifications' => 'boolean',
        ];
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function phones()
    {
        return $this->hasMany(Phone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function pets()
    {
        return $this->hasMany(Pet::class);
    }

    public function livePets()
    {
        return $this->pets()->whereNull('death_date');
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

    public function getLastNameAttribute()
    {
        return trim("{$this->apellido_paterno} {$this->apellido_materno}");
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
