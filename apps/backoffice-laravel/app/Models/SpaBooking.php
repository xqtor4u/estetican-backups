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
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['pet_id', 'operator_id', 'branch_id', 'created_by_user_id', 'scheduled_at', 'duration_minutes', 'status', 'total_estimated_price', 'notes', 'cancellation_reason', 'order_series_id', 'order_folio', 'series_id', 'series_original_at', 'series_move_reason', 'series_confirmed_at'])]
#[ObservedBy(SpaBookingObserver::class)]
class SpaBooking extends Model
{
    /** Estados en los que la cita ya no se va a prestar — liberan su jaula/recurso. */
    public const NOT_PERFORMED_STATUSES = ['cancelled', 'no_show', 'unfulfillable'];

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
            'series_original_at' => 'datetime',
            'series_confirmed_at' => 'datetime',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** ZEUS-047: serie recurrente a la que pertenece (null = cita suelta). */
    public function series(): BelongsTo
    {
        return $this->belongsTo(SpaBookingSeries::class, 'series_id');
    }

    /**
     * ZEUS-047: citas futuras de series recurrentes que nadie ha fijado — la lista "por fijar".
     * Regla de Tomas (07/10/2026): toda cita recurrente se confirma una por una con "Fijar",
     * sin importar el estado de la serie — no se sabe si el cliente va a ir hasta confirmarlo.
     */
    public function scopeSeriesTentative(Builder $query): Builder
    {
        return $query->whereNotNull('series_id')
            ->whereNull('series_confirmed_at')
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now());
    }

    /**
     * ZEUS-047: cita pre-programada — es de una serie recurrente y todavía no se fijó con "Fijar"
     * (se confirma una por una, sin importar el estado de la serie). Aparta horario, se pinta
     * tenue y no manda recordatorio.
     */
    public function isSeriesTentative(): bool
    {
        return $this->series_id && ! $this->series_confirmed_at;
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

        // EST-023 (auditoría 14/09/2026): usa activeOperatorId(), no la columna cruda — con
        // "¿Es personal operativo?" apagado el vínculo histórico se conserva, pero deja de
        // autorizar agenda propia en vivo.
        $operatorId = $user->activeOperatorId();

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
     * Suma de todo lo cobrado por esta cita. SYNC-098: desde que `payments` es la tabla única
     * canónica de cobro (camino móvil Y web), esto es solo la suma de los Payment ligados al
     * SpaBooking — ya no hay que mezclar CashLedger/BankLedger del presupuesto aceptado.
     * Los Payment negativos de reembolso (cancelación tipo refund) restan aquí de forma natural.
     */
    public function totalPaid(): float
    {
        return (float) $this->payments->sum('amount');
    }

    /**
     * Cargos de la cita — FUENTE ÚNICA de qué se cobra (A2, 27/09/2026). Antes cada vista armaba
     * su propia lista y su propio total (presupuesto aceptado, `total_estimated_price` o suma de
     * líneas) y podían no coincidir entre pantallas. Regla: líneas de servicio cobrables (sin
     * canceladas ni "no realizadas") más líneas de artículo. Al aceptar un presupuesto sus
     * renglones se copian a estas líneas, así que ya lo incluyen y además reflejan
     * cancelaciones posteriores. Solo si la cita no tiene ninguna línea se usan los renglones
     * del presupuesto aceptado.
     *
     * @return Collection<int, array{name: string, quantity: float, amount: float}>
     */
    public function chargeLines(): Collection
    {
        $services = $this->services->whereNull('cancelled_at')->whereNull('not_performed_at')
            ->map(fn ($line) => [
                'name' => (string) ($line->service_name_snapshot ?? $line->service?->name ?? 'Servicio'),
                'quantity' => (float) ($line->quantity ?? 1),
                'amount' => round((float) $line->current_price, 2),
            ]);
        $items = $this->items->map(fn ($line) => [
            'name' => (string) ($line->item_name_snapshot ?? $line->item?->name ?? 'Artículo'),
            'quantity' => (float) ($line->quantity ?? 1),
            'amount' => round((float) $line->current_price, 2),
        ]);
        $lines = $services->concat($items)->values();

        if ($lines->isNotEmpty() || $this->services->isNotEmpty()) {
            return $lines;
        }

        $acceptedQuote = $this->quotes->firstWhere('status', 'accepted');

        return collect($acceptedQuote?->items ?? [])->map(fn ($item) => [
            'name' => (string) $item->name(),
            'quantity' => (float) $item->quantity,
            'amount' => round((float) $item->lineTotal(), 2),
        ])->values();
    }

    /**
     * Total a cobrar — la suma de `chargeLines()`. Sin ninguna línea ni presupuesto (cita
     * recién creada sin servicios), cae al total estimado guardado.
     */
    public function chargesTotal(): float
    {
        $lines = $this->chargeLines();

        if ($lines->isEmpty() && $this->services->isEmpty()) {
            $acceptedQuote = $this->quotes->firstWhere('status', 'accepted');

            return round((float) ($acceptedQuote?->total_amount ?? $this->total_estimated_price ?? 0), 2);
        }

        return round((float) $lines->sum('amount'), 2);
    }

    /** Lo cobrado como anticipo (categoría `advance`) — distinto de todo lo pagado. */
    public function advancePaid(): float
    {
        return round((float) $this->payments->where('category', 'advance')->sum('amount'), 2);
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

        return max(0, round($this->chargesTotal() - $this->totalPaid(), 2));
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
