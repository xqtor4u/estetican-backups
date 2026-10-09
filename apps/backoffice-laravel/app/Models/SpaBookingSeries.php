<?php

namespace App\Models;

use App\Domain\Planning\Series\RecurrenceRule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ZEUS-047: serie de citas recurrentes. Ver migración `create_spa_booking_series_table`.
 */
#[Fillable([
    'pet_id',
    'branch_id',
    'created_by_user_id',
    'reviewed_by_user_id',
    'reviewed_at',
    'status',
    'rule',
    'template',
    'starts_at',
    'ends_on',
    'paused_from',
    'paused_until',
    'cancelled_at',
    'cancelled_by_user_id',
    'skipped_occurrences',
    'notes',
])]
class SpaBookingSeries extends Model
{
    /** Pre-programada: aparta horario en la agenda pero no manda recordatorios. */
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'spa_booking_series';

    protected function casts(): array
    {
        return [
            'rule' => 'array',
            'template' => 'array',
            'skipped_occurrences' => 'array',
            'starts_at' => 'datetime',
            'ends_on' => 'date',
            'reviewed_at' => 'datetime',
            'paused_from' => 'date',
            'paused_until' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(SpaBooking::class, 'series_id')->orderBy('scheduled_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SpaBookingSeriesEvent::class, 'series_id')->latest('id');
    }

    /** Pre-programada o activa: las dos generan citas (la revisión de la serie ya no confirma nada). */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_REVIEW, self::STATUS_ACTIVE], true)
            && $this->ends_on->gte(today());
    }

    public function isPausedOn(\DateTimeInterface $day): bool
    {
        return $this->paused_from && $this->paused_until
            && $this->paused_from->lte($day) && $this->paused_until->gte($day);
    }

    /** Pestañas de Agenda → Series recurrentes (ZEUS-047 Fase 3). */
    public function scopeTab(Builder $query, string $tab): Builder
    {
        $open = fn (Builder $q) => $q->whereIn('status', [self::STATUS_PENDING_REVIEW, self::STATUS_ACTIVE])
            ->whereDate('ends_on', '>=', today());
        $paused = fn (Builder $q) => $q->whereDate('paused_from', '<=', today())->whereDate('paused_until', '>=', today());

        return match ($tab) {
            'pausa' => $query->where($open)->where($paused),
            'terminan' => $query->where($open)->whereBetween('ends_on', [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()]),
            'terminadas' => $query->where(fn (Builder $q) => $q->whereIn('status', [self::STATUS_ENDED, self::STATUS_CANCELLED])
                ->orWhereDate('ends_on', '<', today())),
            default => $query->where($open)->where(fn (Builder $q) => $q->whereNull('paused_from')
                ->orWhereDate('paused_from', '>', today())
                ->orWhereDate('paused_until', '<', today())),
        };
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === self::STATUS_CANCELLED => 'Cancelada',
            $this->status === self::STATUS_ENDED || $this->ends_on->lt(today()) => 'Terminada',
            $this->isPausedOn(today()) => 'En pausa hasta '.$this->paused_until->format('d/m/Y'),
            default => 'Activa',
        };
    }

    public function recurrenceRule(): RecurrenceRule
    {
        return RecurrenceRule::fromArray($this->rule ?? []);
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    /** Citas futuras que siguen en pie — el "restan N" que se muestra en la agenda. */
    public function remainingCount(): int
    {
        return $this->bookings()
            ->where('scheduled_at', '>=', now())
            ->whereNotIn('status', [...SpaBooking::NOT_PERFORMED_STATUSES, 'completed'])
            ->count();
    }
}
