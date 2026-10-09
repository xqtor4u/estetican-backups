<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ZEUS-047 Fase 3: bitácora de una serie recurrente. Las citas descartadas o perdidas se borran
 * de la agenda; este registro es lo que queda de ellas (y lo que lee el reporte diario).
 */
#[Fillable([
    'series_id',
    'spa_booking_id',
    'type',
    'scheduled_at',
    'user_id',
    'details',
])]
class SpaBookingSeriesEvent extends Model
{
    /** Alguien convirtió la cita virtual en cita real. */
    public const PINNED = 'pinned';

    /** Alguien avisó que no se atenderá: se borró de la agenda. */
    public const DISCARDED = 'discarded';

    /** Pasó su día sin que nadie la fijara: se borró en el cierre diario. */
    public const EXPIRED = 'expired';

    public const PAUSED = 'paused';

    public const CANCELLED = 'cancelled';

    public const EXTENDED = 'extended';

    public const LABELS = [
        self::PINNED => 'Fijada',
        self::DISCARDED => 'Descartada',
        self::EXPIRED => 'Perdida (nadie la fijó)',
        self::PAUSED => 'Serie en pausa',
        self::CANCELLED => 'Serie cancelada',
        self::EXTENDED => 'Vigencia extendida',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(SpaBookingSeries::class, 'series_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }
}
