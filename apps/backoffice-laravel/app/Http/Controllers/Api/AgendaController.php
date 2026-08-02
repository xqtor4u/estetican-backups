<?php

namespace App\Http\Controllers\Api;

use App\Domain\Planning\Services\OperatorAvailabilityChecker;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AgendaController extends Controller
{
    /** @return array{0: Carbon, 1: Carbon} */
    private function resolveRange(string $view, Carbon $anchor): array
    {
        if ($view === 'week') {
            $rangeStart = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $rangeEnd = $rangeStart->copy()->addDays(6)->endOfDay();
        } elseif ($view === 'month') {
            $rangeStart = $anchor->copy()->startOfMonth();
            $rangeEnd = $anchor->copy()->endOfMonth()->endOfDay();
        } else {
            $rangeStart = $anchor->copy()->startOfDay();
            $rangeEnd = $anchor->copy()->endOfDay();
        }

        return [$rangeStart, $rangeEnd];
    }

    public function index(Request $request)
    {
        $view = $request->query('view', 'day');
        if (! in_array($view, ['day', 'week', 'month'], true)) {
            $view = 'day';
        }

        $anchor = Carbon::parse($request->query('date', now()->toDateString()));
        $operatorId = $request->query('operator_id');

        [$rangeStart, $rangeEnd] = $this->resolveRange($view, $anchor);

        $bookings = SpaBooking::whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
            ->whereNotIn('status', ['cancelled'])
            ->when($operatorId, fn ($q) => $q->where('operator_id', $operatorId))
            ->with([
                'pet:id,name,species,breed,profile_photo_path,client_id',
                'pet.client:id,first_name,apellido_paterno,apellido_materno',
                'services.service:id,name,type',
                'operator:id,first_name,apellido_paterno,apellido_materno,profile_photo_path',
                'quotes' => fn ($q) => $q->where('status', 'accepted')
                    ->with(['items.operator:id,first_name,apellido_paterno,apellido_materno,profile_photo_path']),
            ])
            ->orderBy('scheduled_at')
            ->get();

        return response()->json($bookings->map(function (SpaBooking $b) {
            // Operadores del presupuesto aceptado, más el operador asignado directamente
            // a la cita (spa_bookings.operator_id) por si aún no hay presupuesto aceptado —
            // sin esta unión, una cita recién creada desaparece del filtro por operador.
            $operators = collect();
            $accepted = $b->quotes->first();
            if ($accepted) {
                $operators = $accepted->items
                    ->filter(fn ($i) => $i->operator)
                    ->map(fn ($i) => [
                        'id' => $i->operator->id,
                        'name' => $i->operator->full_name,
                        'photo_url' => $this->operatorPhoto($i->operator),
                    ])
                    ->unique('id');
            }

            if ($b->operator) {
                $operators = $operators->push([
                    'id' => $b->operator->id,
                    'name' => $b->operator->full_name,
                    'photo_url' => $this->operatorPhoto($b->operator),
                ]);
            }

            $operators = $operators->unique('id')->values();

            $endTime = $b->duration_minutes
                ? $b->scheduled_at->copy()->addMinutes($b->duration_minutes)->format('H:i')
                : null;

            return [
                'id' => $b->id,
                'scheduled_at' => $b->scheduled_at,
                'date' => $b->scheduled_at->format('Y-m-d'),
                'time' => $b->scheduled_at->format('H:i'),
                'end_time' => $endTime,
                'duration_minutes' => $b->duration_minutes,
                'status' => $b->status,
                'notes' => $b->notes,
                'total' => $b->total_estimated_price,
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
                ])->values(),
                'operators' => $operators,
            ];
        }));
    }

    /** Bloqueos de no-disponibilidad de operador que se traslapan con el rango pedido */
    public function unavailabilities(Request $request, OperatorAvailabilityChecker $checker)
    {
        $view = $request->query('view', 'day');
        if (! in_array($view, ['day', 'week', 'month'], true)) {
            $view = 'day';
        }

        $anchor = Carbon::parse($request->query('date', now()->toDateString()));
        $operatorId = $request->query('operator_id') ? (int) $request->query('operator_id') : null;

        [$rangeStart, $rangeEnd] = $this->resolveRange($view, $anchor);

        $windows = $checker->unavailabilityWindows($rangeStart, $rangeEnd, $operatorId);

        return response()->json($windows->map(fn ($w) => [
            'operator_id' => $w->operator_id,
            'operator_name' => $w->operator?->full_name,
            'starts_at' => $w->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $w->ends_at->format('Y-m-d H:i:s'),
            'reason' => $w->reason,
        ]));
    }

    /**
     * Citas que requieren revisión: abiertas de días anteriores sin resolver
     * (scheduled/work_order — como ya hacía), citas de HOY que siguen
     * "Programada" mucho después de su hora (nunca se llegaron a iniciar —
     * más grave que "en proceso sin cerrar", porque ni siquiera eso pasó),
     * dos anomalías propias de "en proceso" (quedó abierta mucho después de
     * su duración esperada del mismo día — probablemente se le olvidó
     * cerrar; o su hora programada quedó en el futuro — normalmente una
     * reprogramación indebida de una cita ya iniciada), y citas ya
     * "completed" con saldo pendiente de cobro (cerradas a propósito sin
     * cobrar del todo — el staff volverá a cobrar después — pero el pasivo
     * no debe perderse de vista).
     */
    public function vencidas()
    {
        $now = now();
        $graceMinutes = (int) (app(SystemSettings::class)->all()['booking_grace_minutes'] ?? 15);

        $bookings = SpaBooking::where(function ($q) use ($now, $graceMinutes) {
                $q->where(function ($q1) use ($now) {
                    $q1->whereIn('status', ['scheduled', 'work_order'])
                        ->where('scheduled_at', '<', $now->copy()->startOfDay());
                })
                    ->orWhere(function ($q2) use ($now, $graceMinutes) {
                        $q2->where('status', 'scheduled')
                            ->where('scheduled_at', '>=', $now->copy()->startOfDay())
                            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL ? MINUTE) < ?', [$graceMinutes, $now]);
                    })
                    ->orWhere(function ($q3) use ($now) {
                        $q3->where('status', 'work_order')->where('scheduled_at', '>', $now);
                    })
                    ->orWhere(function ($q4) use ($now) {
                        $q4->where('status', 'work_order')
                            ->whereNotNull('duration_minutes')
                            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) < ?', [$now]);
                    })
                    ->orWhere(function ($q5) {
                        $q5->where('status', 'completed')
                            ->whereRaw(
                                'total_estimated_price > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payable_type = ? AND payable_id = spa_bookings.id)',
                                [SpaBooking::class]
                            );
                    });
            })
            ->with([
                'pet:id,name,species,breed,profile_photo_path,client_id',
                'pet.client:id,first_name,apellido_paterno,apellido_materno',
                'services.service:id,name,type',
            ])
            ->orderBy('scheduled_at')
            ->get();

        $paidByBooking = Payment::where('payable_type', SpaBooking::class)
            ->whereIn('payable_id', $bookings->pluck('id'))
            ->get()
            ->groupBy('payable_id')
            ->map(fn ($payments) => $payments->sum('amount'));

        return response()->json($bookings->map(function (SpaBooking $b) use ($now, $paidByBooking) {
            $endsAt = $b->duration_minutes ? $b->scheduled_at->copy()->addMinutes($b->duration_minutes) : null;
            $paid = (float) ($paidByBooking[$b->id] ?? 0);
            $balance = max(0, (float) $b->total_estimated_price - $paid);

            $reason = match (true) {
                $b->status === 'completed' => 'pending_balance',
                $b->status === 'work_order' && $b->scheduled_at->greaterThan($now) => 'future',
                $b->status === 'work_order' && $endsAt && $endsAt->lessThan($now) => 'overdue',
                $b->status === 'scheduled' && $b->scheduled_at->isToday() => 'not_started',
                default => 'stale_day',
            };

            return [
                'id' => $b->id,
                'scheduled_at' => $b->scheduled_at,
                'time' => $b->scheduled_at->format('H:i'),
                'date_label' => $b->scheduled_at->translatedFormat('D j M'),
                'end_time' => $endsAt?->format('H:i'),
                'status' => $b->status,
                'reason' => $reason,
                'balance' => $balance,
                'notes' => $b->notes,
                'total' => $b->total_estimated_price,
                'pet' => [
                    'id' => $b->pet->id,
                    'name' => $b->pet->name,
                    'photo' => $b->pet->profile_photo_path
                        ? Storage::disk('public')->url($b->pet->profile_photo_path)
                        : null,
                ],
                'client' => $b->pet->client ? [
                    'id' => $b->pet->client->id,
                    'name' => trim($b->pet->client->first_name.' '.$b->pet->client->last_name),
                ] : null,
                'services' => $b->services->map(fn ($s) => [
                    'name' => $s->service?->name ?? '—',
                ])->values(),
            ];
        }));
    }

    private function operatorPhoto($operator): ?string
    {
        if (! $operator->profile_photo_path) {
            return null;
        }

        return Storage::disk('public')->url($operator->profile_photo_path);
    }
}
