<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Domain\Clinical\Services\VaccinationEligibilityChecker;
use App\Domain\Inventory\Contracts\BookingStockConsumptionServiceInterface;
use App\Domain\Planning\Services\OperatorAvailabilityChecker;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Support\Geo\CoverageChecker;
use App\Support\SystemSettings\BusinessHours;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class BookingController extends Controller
{
    public function __construct(
        private BusinessHours $businessHours,
        private OperatorAvailabilityChecker $operatorAvailabilityChecker,
        private CoverageChecker $coverageChecker,
        private VaccinationEligibilityChecker $vaccinationChecker
    ) {}

    /**
     * Único caso de disponibilidad que se puede forzar: el operador fuera de su horario
     * declarado. Conflicto de agenda y vacaciones/permiso siguen siendo bloqueos duros.
     */
    private function canOverrideSchedule(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('agenda.forzar_horario') || $user?->is_super_admin);
    }

    /** Serializa una cita al formato que usa la app móvil */
    private function serialize(SpaBooking $b): array
    {
        $b->loadMissing([
            'pet:id,name,species,breed,profile_photo_path,client_id',
            'pet.client:id,first_name,apellido_paterno,apellido_materno',
            'services.service:id,name,type,price,duration_minutes',
            'services.operator:id,first_name,apellido_paterno,apellido_materno,name',
            'operator:id,first_name,apellido_paterno,apellido_materno,profile_photo_path',
        ]);

        $endTime = $b->duration_minutes
            ? $b->scheduled_at->copy()->addMinutes($b->duration_minutes)->format('H:i')
            : null;

        return [
            'id' => $b->id,
            'order_folio' => $b->order_folio,
            'scheduled_at' => $b->scheduled_at->format('Y-m-d H:i:s'),
            'time' => $b->scheduled_at->format('H:i'),
            'end_time' => $endTime,
            'duration_minutes' => $b->duration_minutes,
            'status' => $b->status,
            'notes' => $b->notes,
            'cancellation_reason' => $b->cancellation_reason,
            'total' => (float) ($b->total_estimated_price ?? 0),
            'pet' => [
                'id' => $b->pet->id,
                'name' => $b->pet->name,
                'species' => $b->pet->species,
                'breed' => $b->pet->breed,
                'photo' => $b->pet->profile_photo_path
                    ? Storage::disk('public')->url($b->pet->profile_photo_path)
                    : null,
            ],
            'client' => $b->pet->client ? [
                'id' => $b->pet->client->id,
                'name' => trim($b->pet->client->first_name.' '.$b->pet->client->last_name),
            ] : null,
            'services' => $b->services->map(fn ($s) => [
                'id' => $s->service?->id,
                'booking_service_id' => $s->id,
                'name' => $s->service?->name ?? '—',
                'type' => $s->service?->type,
                'price' => (float) ($s->current_price ?? $s->service?->price ?? 0),
                'duration_minutes' => $s->service?->duration_minutes,
                'operator_id' => $s->operator_id,
                'operator_name' => $s->operator?->name,
                'is_external' => (bool) $s->is_external,
                'external_cost' => $s->external_cost !== null ? (float) $s->external_cost : null,
            ])->values(),
            'operator' => $b->operator ? [
                'id' => $b->operator->id,
                'name' => $b->operator->full_name,
                'photo_url' => $b->operator->profile_photo_path
                    ? Storage::disk('public')->url($b->operator->profile_photo_path)
                    : null,
            ] : null,
        ];
    }

    public function show(SpaBooking $booking)
    {
        return response()->json($this->serialize($booking));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'operator_id' => 'required|exists:operators,id',
            'scheduled_at' => 'required|date_format:Y-m-d H:i:s',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'services' => 'nullable|array',
            'services.*.id' => 'required_with:services|integer|exists:services,id',
            'services.*.price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'override_availability' => 'nullable|boolean',
        ]);

        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $durationMinutes = (int) ($data['duration_minutes'] ?? 30);

        if (! $this->businessHours->isWithin($scheduledAt)) {
            return response()->json([
                'message' => "La hora elegida está fuera del horario operativo ({$this->businessHours->openingTime()}–{$this->businessHours->closingTime()}).",
            ], 422);
        }

        if ($this->operatorAvailabilityChecker->hasConflict((int) $data['operator_id'], $scheduledAt, $durationMinutes)) {
            return response()->json(['message' => 'El operador seleccionado ya tiene una cita en ese horario.'], 422);
        }

        $overrideSchedule = ! empty($data['override_availability']) && $this->canOverrideSchedule();
        if (! $overrideSchedule && $this->operatorAvailabilityChecker->isOutsideWorkingHours((int) $data['operator_id'], $scheduledAt, $durationMinutes)) {
            return response()->json(['message' => 'El operador seleccionado no labora en el horario indicado.'], 422);
        }

        if ($this->operatorAvailabilityChecker->hasTimeOff((int) $data['operator_id'], $scheduledAt, $durationMinutes)) {
            return response()->json(['message' => 'El operador seleccionado no está disponible en ese periodo (vacaciones/permiso).'], 422);
        }

        $serviceRows = collect($data['services'] ?? []);
        $serviceIds = $serviceRows->pluck('id')->all();
        $catalogPrices = $serviceIds ? Service::whereIn('id', $serviceIds)->pluck('price', 'id') : collect();
        // El precio sugerido del catálogo es editable por el usuario al agendar; si no manda
        // uno explícito (o el campo es null), cae al precio del catálogo.
        $resolvedPrices = $serviceRows->mapWithKeys(fn ($row) => [
            $row['id'] => $row['price'] ?? ($catalogPrices[$row['id']] ?? 0),
        ]);
        $estimatedTotal = $resolvedPrices->sum();

        $booking = SpaBooking::create([
            'pet_id' => $data['pet_id'],
            'operator_id' => $data['operator_id'],
            'created_by_user_id' => auth()->id(),
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'status' => 'scheduled',
            'total_estimated_price' => $estimatedTotal,
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($serviceIds as $svcId) {
            SpaBookingService::create([
                'spa_booking_id' => $booking->id,
                'service_id' => $svcId,
                'current_price' => $resolvedPrices[$svcId] ?? 0,
            ]);
        }

        $coverageWarning = $this->coverageChecker->checkPet($booking->pet);
        $vaccinationWarning = $this->vaccinationChecker->check($booking->pet);

        return response()->json([
            ...$this->serialize($booking),
            'coverage_warning' => $coverageWarning
                ? "Esta mascota está a {$coverageWarning['distance_km']} km de {$coverageWarning['branch_name']}, fuera del radio de cobertura de {$coverageWarning['radius_km']} km."
                : null,
            'vaccination_warning' => $vaccinationWarning
                ? 'Esta mascota no tiene vigente: '.implode(', ', $vaccinationWarning['missing_vaccines']).'.'
                : null,
        ], 201);
    }

    public function update(Request $request, SpaBooking $booking)
    {
        // No permite editar citas ya completadas o canceladas, salvo que el único
        // campo que venga en el request sea `notes` — corregir/completar una nota
        // sobre un servicio ya cerrado no reabre nada operativo (horario, servicios,
        // estado), así que no tiene por qué estar sujeto a esta regla.
        $onlyNotes = array_keys($request->all()) === ['notes'];
        if (in_array($booking->status, ['completed', 'cancelled']) && ! $onlyNotes) {
            return response()->json(['message' => 'No se puede editar una cita '.$booking->status.'.'], 422);
        }

        $data = $request->validate([
            'operator_id' => 'sometimes|exists:operators,id',
            'scheduled_at' => 'sometimes|date_format:Y-m-d H:i:s',
            'duration_minutes' => 'sometimes|nullable|integer|min:15|max:480',
            'status' => 'sometimes|in:scheduled,work_order,completed,cancelled,no_show',
            'services' => 'sometimes|array',
            'services.*' => 'integer|exists:services,id',
            'notes' => 'sometimes|nullable|string|max:1000',
            'cancellation_reason' => 'sometimes|nullable|string|max:500',
            'override_availability' => 'nullable|boolean',
        ]);

        // Re-validar horario/traslape solo si el request realmente reprograma la cita
        if (array_key_exists('scheduled_at', $data)) {
            $resolvedOperatorId = $data['operator_id'] ?? $booking->operator_id;

            if (! $resolvedOperatorId) {
                return response()->json(['message' => 'Debes asignar un operador para poder reprogramar esta cita.'], 422);
            }

            $scheduledAt = Carbon::parse($data['scheduled_at']);
            $durationMinutes = (int) ($data['duration_minutes'] ?? $booking->duration_minutes ?? 30);

            if (! $this->businessHours->isWithin($scheduledAt)) {
                return response()->json([
                    'message' => "La hora elegida está fuera del horario operativo ({$this->businessHours->openingTime()}–{$this->businessHours->closingTime()}).",
                ], 422);
            }

            if ($this->operatorAvailabilityChecker->hasConflict((int) $resolvedOperatorId, $scheduledAt, $durationMinutes, $booking->id)) {
                return response()->json(['message' => 'El operador seleccionado ya tiene una cita en ese horario.'], 422);
            }

            $overrideSchedule = ! empty($data['override_availability']) && $this->canOverrideSchedule();
            if (! $overrideSchedule && $this->operatorAvailabilityChecker->isOutsideWorkingHours((int) $resolvedOperatorId, $scheduledAt, $durationMinutes)) {
                return response()->json(['message' => 'El operador seleccionado no labora en el horario indicado.'], 422);
            }

            if ($this->operatorAvailabilityChecker->hasTimeOff((int) $resolvedOperatorId, $scheduledAt, $durationMinutes)) {
                return response()->json(['message' => 'El operador seleccionado no está disponible en ese periodo (vacaciones/permiso).'], 422);
            }
        }

        // Campos escalares — solo se tocan los que realmente vinieron en el payload,
        // para poder limpiar notes/cancellation_reason a null explícitamente sin que
        // un array_filter por null los descarte antes de llegar a fill().
        $fillData = [];
        foreach (['operator_id', 'scheduled_at', 'duration_minutes', 'status', 'notes', 'cancellation_reason'] as $field) {
            if (array_key_exists($field, $data)) {
                $fillData[$field] = $data[$field];
            }
        }
        $booking->fill($fillData);

        // Sincronizar servicios si vienen en el payload — preserva las líneas existentes
        // (precio editado, operador y costo externo asignados) en vez de borrar y recrear todo;
        // solo quita las líneas que ya no están seleccionadas y agrega las nuevas al precio de catálogo.
        if (array_key_exists('services', $data)) {
            $serviceIds = $data['services'];
            $prices = $serviceIds ? Service::whereIn('id', $serviceIds)->pluck('price', 'id') : collect();
            $existingServiceIds = $booking->services()->pluck('service_id')->all();

            $booking->services()->whereNotIn('service_id', $serviceIds)->delete();
            foreach ($serviceIds as $svcId) {
                if (in_array($svcId, $existingServiceIds, true)) {
                    continue;
                }
                SpaBookingService::create([
                    'spa_booking_id' => $booking->id,
                    'service_id' => $svcId,
                    'current_price' => $prices[$svcId] ?? 0,
                ]);
            }
            $booking->total_estimated_price = $booking->services()->sum('current_price');
        }

        $booking->save();

        // Generar folio de orden al iniciar el trabajo
        if (($data['status'] ?? null) === 'work_order' && ! $booking->order_folio) {
            try {
                app(AccountingServiceInterface::class)->assignOrderFolio($booking);
            } catch (\Throwable) {
                // No interrumpir el cambio de estado si falla la asignación del folio
            }
        }

        if (($data['status'] ?? null) === 'completed') {
            app(BookingStockConsumptionServiceInterface::class)->consume($booking, auth()->id());
        }

        return response()->json($this->serialize($booking->fresh()));
    }

    /** Asigna profesional / costo externo a una línea de servicio específica de la cita (mismo mecanismo que el work order web). */
    public function assignServiceProfessional(Request $request, SpaBooking $booking, SpaBookingService $line)
    {
        abort_unless($line->spa_booking_id === $booking->id, 404);

        $validated = $request->validate([
            'operator_id' => 'required|exists:operators,id',
            'external_cost' => 'nullable|numeric|min:0',
            'current_price' => 'nullable|numeric|min:0',
        ]);

        $line->update([
            'operator_id' => $validated['operator_id'],
            'is_external' => $request->boolean('is_external'),
            'external_cost' => $validated['external_cost'] ?? null,
            'current_price' => $validated['current_price'] ?? $line->current_price,
        ]);

        return response()->json($this->serialize($booking->fresh()));
    }
}
