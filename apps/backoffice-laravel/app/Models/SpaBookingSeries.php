<?php

namespace App\Models;

use App\Domain\Planning\Series\RecurrenceRule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
