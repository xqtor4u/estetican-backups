<?php

namespace App\Models;

use App\Support\SystemSettings\SystemSettings;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SystemSetting extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['section', 'key', 'value'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('configuracion');
    }

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