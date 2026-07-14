<?php

namespace App\Http\Controllers\Api;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
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
        private CoverageChecker $coverageChecker
    ) {}

    /** Serializa una cita al formato que usa la app móvil */
    private function serialize(SpaBooking $b): array
    {
        $b->loadMissing([
            'pet:id,name,species,breed,profile_photo_path,client_id',
            'pet.client:id,first_name,last_name',
            'services.service:id,name,type,price,duration_minutes',
            'operator:id,full_name,profile_photo_path',
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
                'name' => $s->service?->name ?? '—',
                'type' => $s->service?->type,
                'price' => (float) ($s->current_price ?? $s->service?->price ?? 0),
                'duration_minutes' => $s->service?->duration_minutes,
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
            'services.*' => 'integer|exists:services,id',
            'notes' => 'nullable|string|max:1000',
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

        $serviceIds = $data['services'] ?? [];
        $prices = $serviceIds ? Service::whereIn('id', $serviceIds)->pluck('price', 'id') : collect();
        $estimatedTotal = $prices->sum();

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
                'current_price' => $prices[$svcId] ?? 0,
            ]);
        }

        $coverageWarning = $this->coverageChecker->checkPet($booking->pet);

        return response()->json([
            ...$this->serialize($booking),
            'coverage_warning' => $coverageWarning
                ? "Esta mascota está a {$coverageWarning['distance_km']} km de {$coverageWarning['branch_name']}, fuera del radio de cobertura de {$coverageWarning['radius_km']} km."
                : null,
        ], 201);
    }

    public function update(Request $request, SpaBooking $booking)
    {
        // No permite editar citas ya completadas o canceladas
        if (in_array($booking->status, ['completed', 'cancelled'])) {
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
        }

        // Campos escalares
        $booking->fill(array_filter([
            'operator_id' => $data['operator_id'] ?? $booking->operator_id,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'duration_minutes' => array_key_exists('duration_minutes', $data) ? $data['duration_minutes'] : $booking->duration_minutes,
            'status' => $data['status'] ?? null,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $booking->notes,
            'cancellation_reason' => array_key_exists('cancellation_reason', $data) ? $data['cancellation_reason'] : $booking->cancellation_reason,
        ], fn ($v) => $v !== null));

        // Sincronizar servicios si vienen en el payload
        if (array_key_exists('services', $data)) {
            $serviceIds = $data['services'];
            $prices = $serviceIds ? Service::whereIn('id', $serviceIds)->pluck('price', 'id') : collect();

            $booking->services()->delete();
            foreach ($serviceIds as $svcId) {
                SpaBookingService::create([
                    'spa_booking_id' => $booking->id,
                    'service_id' => $svcId,
                    'current_price' => $prices[$svcId] ?? 0,
                ]);
            }
            $booking->total_estimated_price = $prices->sum();
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

        return response()->json($this->serialize($booking->fresh()));
    }
}
