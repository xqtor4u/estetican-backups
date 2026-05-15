<?php

namespace App\Models;

use App\Support\CatalogCache\PetCatalogCache;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Pet extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(static function (): void {
            PetCatalogCache::flushSpeciesOptions();
        });

        static::deleted(static function (): void {
            PetCatalogCache::flushSpeciesOptions();
        });
    }

    protected $fillable = [
        'client_id',
        'name',
        'species',
        'breed',
        'birth_date',
        'death_date',
        'microchip_code',
        'tattoo_code',
        'sex',
        'coat_color',
        'size',
        'profile_photo_path',
        'is_sterilized',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'death_date' => 'date',
        'is_sterilized' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function medicalAlerts()
    {
        return $this->hasMany(PetMedicalAlert::class);
    }

    public function photos()
    {
        return $this->hasMany(PetPhoto::class);
    }

    public function spaBookings(): HasMany
    {
        return $this->hasMany(SpaBooking::class);
    }

    public function hotelReservations(): HasMany
    {
        return $this->hasMany(HotelReservation::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(Stay::class);
    }

    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    public function primaryPhoto()
    {
        return $this->hasOne(PetPhoto::class)->where('is_primary', true)->latestOfMany();
    }

    public function latestPhoto()
    {
        return $this->hasOne(PetPhoto::class)->latestOfMany();
    }

    public function getCatalogPhotoAttribute(): ?PetPhoto
    {
        $primaryPhoto = $this->relationLoaded('primaryPhoto')
            ? $this->getRelation('primaryPhoto')
            : $this->primaryPhoto()->first();

        if ($primaryPhoto) {
            return $primaryPhoto;
        }

        return $this->relationLoaded('latestPhoto')
            ? $this->getRelation('latestPhoto')
            : $this->latestPhoto()->first();
    }

    public function getCatalogThumbnailUrlAttribute(): ?string
    {
        if ($this->profile_photo_path) {
            if (str_contains($this->profile_photo_path, '/original/')) {
                return Storage::disk('public')->url(str_replace('/original/', '/thumbs/', $this->profile_photo_path));
            }
            return Storage::disk('public')->url($this->profile_photo_path);
        }

        return $this->catalog_photo?->photo_thumbnail_url;
    }

    public function getCatalogPhotoUrlAttribute(): ?string
    {
        if ($this->profile_photo_path) {
            return Storage::disk('public')->url($this->profile_photo_path);
        }

        return $this->catalog_photo?->photo_file_url;
    }

    public function getLastServiceAtAttribute(): ?\Carbon\Carbon
    {
        $lastSpa = $this->spaBookings()->where('scheduled_at', '<=', now())->max('scheduled_at');
        $lastHotel = $this->hotelReservations()->where('start_at', '<=', now())->max('start_at');

        if (!$lastSpa && !$lastHotel) return null;
        if (!$lastSpa) return \Carbon\Carbon::parse($lastHotel);
        if (!$lastHotel) return \Carbon\Carbon::parse($lastSpa);

        return \Carbon\Carbon::parse($lastSpa)->max($lastHotel);
    }

    public function getAgeDescriptionAttribute(): ?string
    {
        if (!$this->birth_date) {
            return null;
        }

        $endDate = $this->death_date ?: now();
        $parts = $this->formatAgeParts($this->birth_date, $endDate);

        if (!$parts) {
            return null;
        }

        return $this->death_date
            ? 'Edad al fallecer: ' . implode(' y ', $parts)
            : 'Edad actual: ' . implode(' y ', $parts);
    }

    public function getSpeciesLabelAttribute(): ?string
    {
        if (!$this->species) {
            return null;
        }

        return ucfirst($this->species);
    }

    private function formatAgeParts(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        if ($endDate->lt($startDate)) {
            return [];
        }

        $diff = $startDate->diff($endDate);
        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y . ' ' . ($diff->y === 1 ? 'año' : 'años');
        }

        if ($diff->m > 0 && count($parts) < 2) {
            $parts[] = $diff->m . ' ' . ($diff->m === 1 ? 'mes' : 'meses');
        }

        if (!$parts && $diff->d > 0) {
            $parts[] = $diff->d . ' ' . ($diff->d === 1 ? 'dia' : 'dias');
        }

        if (!$parts) {
            $parts[] = 'menos de un dia';
        }

        return $parts;
    }
}
