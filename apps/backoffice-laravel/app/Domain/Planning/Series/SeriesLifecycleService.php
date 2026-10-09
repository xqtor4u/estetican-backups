<?php

namespace App\Domain\Planning\Series;

use App\Domain\GoogleCalendar\Services\GoogleCalendarSyncService;
use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Models\SpaBooking;
use App\Models\SpaBookingSeries;
use App\Models\SpaBookingSeriesEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ZEUS-047 Fase 3: vida de una serie después del alta.
 *
 * Regla de Tomas (08/10/2026): una cita de serie sin fijar es **virtual** — solo una propuesta.
 * Alguien la fija (se vuelve cita real), alguien la descarta ("no se atenderá") o, si su día pasa
 * sin que nadie la fije, el cierre diario la borra. Descartadas y perdidas se BORRAN de la agenda;
 * su rastro queda en `spa_booking_series_events`.
 *
 * Nunca se borra ni cancela sola una cita con presupuesto o pago: se deja y se reporta.
 */
class SeriesLifecycleService
{
    public function __construct(
        private readonly ResourceAllocationServiceInterface $resources,
    ) {}

    public function pin(SpaBooking $booking, ?User $user): void
    {
        $booking->update([
            'series_confirmed_at' => now(),
            'series_confirmed_by_user_id' => $user?->id,
        ]);

        $this->log($booking->series_id, SpaBookingSeriesEvent::PINNED, $user, $booking);
    }

    /** Se puede quitar de la agenda sin perder nada: sigue programada y sin presupuesto ni pago. */
    public function isRemovable(SpaBooking $booking): bool
    {
        return $booking->status === 'scheduled'
            && ! $booking->quotes()->exists()
            && ! $booking->payments()->exists();
    }

    /** "No se atenderá": borra la cita virtual. Devuelve false si no es virtual o no se puede quitar. */
    public function discard(SpaBooking $booking, ?User $user, ?string $reason = null): bool
    {
        if (! $booking->isSeriesTentative() || ! $this->isRemovable($booking)) {
            return false;
        }

        DB::transaction(function () use ($booking, $user, $reason) {
            $this->log($booking->series_id, SpaBookingSeriesEvent::DISCARDED, $user, $booking, $reason);
            $this->remove($booking);
        });

        return true;
    }

    /**
     * Pausa por rango: las citas de la serie entre esas fechas salen de la agenda (virtuales →
     * borradas; fijadas → canceladas, liberando operador y jaula). Las de después siguen igual y
     * la serie vuelve sola a "Activas" al pasar `paused_until`.
     *
     * @return Collection<int, SpaBooking> citas que no se tocaron (presupuesto / pago / en proceso)
     */
    public function pause(SpaBookingSeries $series, Carbon $from, Carbon $until, ?User $user): Collection
    {
        return DB::transaction(function () use ($series, $from, $until, $user) {
            $kept = $this->clearBookings($series, $from->copy()->startOfDay(), $until->copy()->endOfDay(), 'Serie en pausa');

            $series->update(['paused_from' => $from->toDateString(), 'paused_until' => $until->toDateString()]);
            $this->log($series->id, SpaBookingSeriesEvent::PAUSED, $user, null, "Del {$from->format('d/m/Y')} al {$until->format('d/m/Y')}");

            return $kept;
        });
    }

    /** @return Collection<int, SpaBooking> citas que no se tocaron */
    public function cancelFrom(SpaBookingSeries $series, Carbon $from, ?User $user): Collection
    {
        return DB::transaction(function () use ($series, $from, $user) {
            $kept = $this->clearBookings($series, $from->copy()->startOfDay(), null, 'Serie cancelada');

            $series->update([
                'status' => SpaBookingSeries::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $user?->id,
            ]);
            $this->log($series->id, SpaBookingSeriesEvent::CANCELLED, $user, null, "A partir del {$from->format('d/m/Y')}");

            return $kept;
        });
    }

    public function logExtension(SpaBookingSeries $series, ?User $user, Carbon $oldEndsOn, int $created): void
    {
        $this->log($series->id, SpaBookingSeriesEvent::EXTENDED, $user, null,
            "Del {$oldEndsOn->format('d/m/Y')} al {$series->ends_on->format('d/m/Y')} · {$created} citas nuevas");
    }

    /**
     * Cierre del día: borra las citas virtuales cuyo día ya terminó (o termina con este cierre) y
     * marca como terminadas las series vencidas. Corre a las 23:50 (`series:cierre-diario`).
     *
     * @return int citas perdidas borradas
     */
    public function closeDay(Carbon $day): int
    {
        $expired = SpaBooking::query()
            ->whereNotNull('series_id')
            ->whereNull('series_confirmed_at')
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', $day->copy()->endOfDay())
            ->get();

        $count = 0;
        foreach ($expired as $booking) {
            if (! $this->isRemovable($booking)) {
                continue;
            }

            DB::transaction(function () use ($booking) {
                $this->log($booking->series_id, SpaBookingSeriesEvent::EXPIRED, null, $booking);
                $this->remove($booking);
            });
            $count++;
        }

        SpaBookingSeries::query()
            ->whereIn('status', [SpaBookingSeries::STATUS_PENDING_REVIEW, SpaBookingSeries::STATUS_ACTIVE])
            ->whereDate('ends_on', '<=', $day->toDateString())
            ->whereDoesntHave('bookings', fn ($q) => $q->where('scheduled_at', '>', $day->copy()->endOfDay())
                ->whereNotIn('status', SpaBooking::NOT_PERFORMED_STATUSES))
            ->update(['status' => SpaBookingSeries::STATUS_ENDED]);

        return $count;
    }

    /**
     * Lo que pasó con las citas de series en ese día, para el reporte por correo.
     *
     * @return array{attended: Collection, pinned: Collection, discarded: Collection, expired: Collection}
     */
    public function dailyReport(Carbon $day): array
    {
        $events = SpaBookingSeriesEvent::query()
            ->with(['series.pet:id,name', 'user:id,name,first_name,apellido_paterno'])
            ->whereIn('type', [SpaBookingSeriesEvent::PINNED, SpaBookingSeriesEvent::DISCARDED, SpaBookingSeriesEvent::EXPIRED])
            ->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy('type');

        $attended = SpaBooking::query()
            ->with(['pet:id,name', 'operator:id,name,first_name,apellido_paterno', 'seriesConfirmedBy:id,name,first_name,apellido_paterno'])
            ->whereNotNull('series_id')
            ->where('status', 'completed')
            ->whereBetween('scheduled_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderBy('scheduled_at')
            ->get();

        return [
            'attended' => $attended,
            'pinned' => $events->get(SpaBookingSeriesEvent::PINNED, collect()),
            'discarded' => $events->get(SpaBookingSeriesEvent::DISCARDED, collect()),
            'expired' => $events->get(SpaBookingSeriesEvent::EXPIRED, collect()),
        ];
    }

    /** @return Collection<int, SpaBooking> */
    private function clearBookings(SpaBookingSeries $series, Carbon $from, ?Carbon $until, string $reason): Collection
    {
        $bookings = $series->bookings()
            ->where('scheduled_at', '>=', $from)
            ->when($until, fn ($q) => $q->where('scheduled_at', '<=', $until))
            ->whereNotIn('status', [...SpaBooking::NOT_PERFORMED_STATUSES, 'completed'])
            ->get();

        $kept = collect();
        foreach ($bookings as $booking) {
            if (! $this->isRemovable($booking)) {
                $kept->push($booking);

                continue;
            }

            if ($booking->isSeriesTentative()) {
                $this->remove($booking);

                continue;
            }

            // Fijada = cita real: se cancela (queda en el historial), no se borra.
            $booking->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);
            $this->resources->releaseSourceAllocations($booking);
        }

        return $kept;
    }

    /** Quita una cita virtual de la agenda: jaula, evento de Google y la cita (sus líneas caen en cascada). */
    private function remove(SpaBooking $booking): void
    {
        $this->resources->releaseSourceAllocations($booking);

        if ($booking->google_event_id) {
            try {
                app(GoogleCalendarSyncService::class)->deleteBookingEvent($booking);
            } catch (Throwable $e) {
                Log::warning('Serie recurrente: no se pudo borrar el evento de Google de una cita virtual.', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $booking->delete();
    }

    private function log(int $seriesId, string $type, ?User $user, ?SpaBooking $booking = null, ?string $details = null): void
    {
        SpaBookingSeriesEvent::create([
            'series_id' => $seriesId,
            'spa_booking_id' => $booking?->id,
            'type' => $type,
            'scheduled_at' => $booking?->scheduled_at,
            'user_id' => $user?->id,
            'details' => $details ? mb_substr($details, 0, 255) : null,
        ]);
    }
}
