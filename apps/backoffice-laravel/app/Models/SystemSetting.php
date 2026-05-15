<?php

namespace App\Models;

use App\Support\SystemSettings\SystemSettings;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'section',
        'key',
        'type',
        'value',
    ];

    protected static function booted(): void
    {
        $flushCache = static function (): void {
            SystemSettings::flushCache();
        };

        static::saved($flushCache);
        static::deleted($flushCache);
    }
}