<?php

namespace App\Models;

use App\Observers\SpaBookingObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['pet_id', 'operator_id', 'created_by_user_id', 'scheduled_at', 'duration_minutes', 'status', 'total_estimated_price', 'notes', 'cancellation_reason', 'order_series_id', 'order_folio'])]
#[ObservedBy(SpaBookingObserver::class)]
class SpaBooking extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('citas-spa');
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'total_estimated_price' => 'decimal:2',
            'google_synced_at' => 'datetime',
        ];
    }

    public function orderSeries(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'order_series_id');
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(SpaBookingService::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SpaBookingItem::class);
    }

    public function executedServices(): HasMany
    {
        return $this->hasMany(ExecutedService::class);
    }

    public function resourceAllocations(): MorphMany
    {
        return $this->morphMany(ResourceAllocation::class, 'source');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BookingMessage::class);
    }

    public function processNotes(): HasMany
    {
        return $this->hasMany(BookingProcessNote::class)->orderBy('created_at');
    }

    /**
     * Acota a las citas que le corresponden a $user: si tiene `agenda.ver_todas` (o es
     * super-admin) no filtra nada. Si no, solo las citas donde es el operador asignado
     * directamente (`operator_id`) o donde aparece como operador de un ítem del presupuesto
     * aceptado — un operador puede quedar asignado por cualquiera de los dos caminos (ver
     * `Api\AgendaController::index()`, misma unión). Sin operador vinculado (`operator_id`
     * nulo en `users`), fuerza vacío en vez de filtrar por `NULL` (que matchearía citas sin
     * operador asignado, no las del usuario).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin || $user->can('agenda.ver_todas')) {
            return $query;
        }

        $operatorId = $user->operator_id;

        if (! $operatorId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($operatorId) {
            $q->where('operator_id', $operatorId)
                ->orWhereHas('quotes', function (Builder $quoteQuery) use ($operatorId) {
                    $quoteQuery->where('status', 'accepted')
                        ->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('operator_id', $operatorId));
                });
        });
    }

    /**
     * Suma de todo lo cobrado por esta cita, sin importar el camino: CashLedger/BankLedger
     * ligados al presupuesto aceptado (camino web) + Payment directo (camino móvil, el más
     * usado en producción real — la mayoría de citas no tiene Quote de por medio). El total
     * "Total" de la tabla de Agenda solo sumaba lo primero y quedaba en $0 para toda cita
     * cobrada desde móvil sin Quote, aunque sí estuviera pagada — mismo patrón de bug ya
     * corregido antes en reports/invoice.blade.php (ver BITACORA 27/07/2026).
     */
    public function totalPaid(): float
    {
        $acceptedQuote = $this->quotes->firstWhere('status', 'accepted');
        $ledgerPaid = $acceptedQuote
            ? (float) $acceptedQuote->cashLedgers->sum('amount') + (float) $acceptedQuote->bankLedgers->sum('amount')
            : 0.0;

        return $ledgerPaid + (float) $this->payments->sum('amount');
    }

    /**
     * Una cita cancelada nunca se llegó a prestar — su total_estimated_price/monto de
     * presupuesto no representa dinero pendiente de verdad, solo lo que se había cotizado
     * antes de cancelar. Mostrarlo como "saldo pendiente" es engañoso (a pedido del usuario,
     * 03/08/2026): si ya se había cobrado algo antes de cancelar (ej. anticipo con
     * penalización), esa transacción real ya quedó registrada y liquidada aparte — no es
     * "pendiente".
     */
    public function unpaidBalance(): float
    {
        if ($this->status === 'cancelled') {
            return 0.0;
        }

        $acceptedQuote = $this->quotes->firstWhere('status', 'accepted');
        $total = $acceptedQuote ? (float) $acceptedQuote->total_amount : (float) $this->total_estimated_price;

        return max(0, $total - $this->totalPaid());
    }

    /**
     * Anomalía que requiere revisión, o null si no hay ninguna. Mismo criterio que
     * `Api\AgendaController::vencidas()` y el `agendaAlertKind()` del móvil — se
     * mantiene aquí como fuente única para no duplicar el umbral en cada vista web.
     * `$graceMinutes` es `booking_grace_minutes` (default 15, misma tolerancia que
     * ya usa "Iniciar cita").
     */
    public function alertReason(int $graceMinutes = 15): ?string
    {
        if ($this->status === 'scheduled') {
            return $this->scheduled_at->copy()->addMinutes($graceMinutes)->isPast() ? 'not_started' : null;
        }

        if ($this->status !== 'work_order') {
            return null;
        }

        if ($this->scheduled_at->isFuture()) {
            return 'future';
        }

        if ($this->duration_minutes && $this->scheduled_at->copy()->addMinutes($this->duration_minutes)->isPast()) {
            return 'overdue';
        }

        return null;
    }
}
