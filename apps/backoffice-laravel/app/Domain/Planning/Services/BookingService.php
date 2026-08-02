<?php

namespace App\Domain\Planning\Services;

use App\Domain\Planning\Contracts\BookingServiceInterface;
use App\Domain\Planning\Contracts\SpaBookingRepositoryInterface;
use App\Models\SpaBooking;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BookingService implements BookingServiceInterface
{
    public function __construct(
        private SpaBookingRepositoryInterface $spaBookingRepository
    ) {}

    public function scheduleSpaSession(int $petId, string $scheduledAt, array $services, ?string $notes = null, ?int $operatorId = null): SpaBooking
    {
        DB::beginTransaction();
        try {
            $totalPrice = 0;
            // services array format: [service_id => price, ...]
            foreach ($services as $price) {
                $totalPrice += $price;
            }

            $booking = $this->spaBookingRepository->create([
                'pet_id' => $petId,
                'operator_id' => $operatorId,
                'created_by_user_id' => auth()->id(),
                'scheduled_at' => $scheduledAt,
                'total_estimated_price' => $totalPrice,
                'status' => 'scheduled',
                'notes' => $notes,
            ]);

            $this->spaBookingRepository->attachServices($booking->id, $services);

            DB::commit();

            return $booking;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function rescheduleBooking(int $bookingId, string $scheduledAt, ?string $notes = null, ?int $operatorId = null): bool
    {
        $booking = $this->spaBookingRepository->findById($bookingId);

        if (! $booking || $booking->status !== 'scheduled') {
            return false;
        }

        $data = [
            'scheduled_at' => $scheduledAt,
            'notes' => $notes,
        ];

        if ($operatorId !== null) {
            $data['operator_id'] = $operatorId;
        }

        return $this->spaBookingRepository->update($bookingId, $data);
    }

    public function cancelBooking(int $bookingId, string $reason): bool
    {
        $booking = $this->spaBookingRepository->findById($bookingId);
        if (! $booking || $booking->status !== 'scheduled') {
            return false;
        }

        return $this->spaBookingRepository->update($bookingId, [
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);
    }

    public function markNoShow(int $bookingId, ?string $reason = null): bool
    {
        $booking = $this->spaBookingRepository->findById($bookingId);

        if (! $booking || ! in_array($booking->status, ['scheduled', 'work_order'], true)) {
            return false;
        }

        return $this->spaBookingRepository->update($bookingId, [
            'status' => 'no_show',
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * A diferencia de no_show (el cliente no llegó — sí es atribuible al cliente),
     * unfulfillable cubre cualquier otro motivo por el que el servicio no se completó
     * (mascota no cooperó, operador se lastimó, etc.) — puede ocurrir tanto antes de
     * empezar como ya iniciado el servicio, por eso acepta ambos estados de origen.
     */
    public function markUnfulfillable(int $bookingId, ?string $reason = null): bool
    {
        $booking = $this->spaBookingRepository->findById($bookingId);

        if (! $booking || ! in_array($booking->status, ['scheduled', 'work_order'], true)) {
            return false;
        }

        return $this->spaBookingRepository->update($bookingId, [
            'status' => 'unfulfillable',
            'cancellation_reason' => $reason,
        ]);
    }

    public function getUpcomingBookings(): Collection
    {
        return $this->spaBookingRepository->getUpcoming();
    }
}
