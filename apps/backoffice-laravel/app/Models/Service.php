<?php

namespace App\Models;

use App\Support\CatalogCache\OperatorServiceCapabilityCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'open_to_all_operators',
    'account_id',
    'type',
    'department',
    'name',
    'description',
    'price',
    'suggested_price',
    'requires_advance',
    'advance_percentage',
    'duration_minutes',
    'suggested_duration_minutes',
    'lead_time_hours',
    'recurrence_days',
    'is_active',
    'is_core_vaccine',
    'ai_visible',
    'is_generic',
    'is_emergency',
])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'suggested_price' => 'decimal:2',
            'advance_percentage' => 'decimal:2',
            'duration_minutes' => 'integer',
            'suggested_duration_minutes' => 'integer',
            'lead_time_hours' => 'integer',
            'recurrence_days' => 'integer',
            'is_active' => 'boolean',
            'requires_advance' => 'boolean',
            'is_core_vaccine' => 'boolean',
            'ai_visible' => 'boolean',
            'is_generic' => 'boolean',
            'is_emergency' => 'boolean',
            'open_to_all_operators' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Cambiar `open_to_all_operators` altera qué operadores son elegibles — invalidar la
        // cache de resolución (SYNC-073).
        static::saved(static function (self $service): void {
            if ($service->wasChanged('open_to_all_operators')) {
                OperatorServiceCapabilityCache::flush();
            }
        });

        static::deleted(static fn () => OperatorServiceCapabilityCache::flush());
    }

    public function executedServiceItems(): HasMany
    {
        return $this->hasMany(ExecutedServiceItem::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function spaBookingServices(): HasMany
    {
        return $this->hasMany(SpaBookingService::class);
    }

    /** Roles de puesto cuya plantilla incluye este servicio (SYNC-073). */
    public function roleTemplates(): HasMany
    {
        return $this->hasMany(OperatorRoleServiceTemplate::class);
    }

    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function groupComponents(): HasMany
    {
        return $this->hasMany(GroupComponent::class);
    }

    /**
     * SYNC-104/ZEUS-027: `quote_items.service_id`, `spa_booking_services.service_id` y
     * `executed_service_items.service_id` son todas `cascadeOnDelete()` — borrar un servicio ya
     * usado en un presupuesto, una cita o un servicio ejecutado real borraría esas líneas
     * históricas en cascada, sin aviso. `ServiceController::destroy()` usa este método para
     * suspender (`is_active = false`, ya filtrado de todos los selectores de alta) en vez de
     * borrar cuando hay uso real — solo se borra de verdad si no hay ninguno.
     */
    public function hasHistoricalUsage(): bool
    {
        return $this->groupComponents()->exists()
            || $this->quoteItems()->exists()
            || $this->spaBookingServices()->exists()
            || $this->executedServiceItems()->exists();
    }
}
