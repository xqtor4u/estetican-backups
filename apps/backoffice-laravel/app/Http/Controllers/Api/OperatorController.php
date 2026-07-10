<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Operator;
use App\Models\OperatorCheckin;
use App\Models\SpaBooking;
use Illuminate\Support\Facades\Storage;

class OperatorController extends Controller
{
    public function index()
    {
        $operators = Operator::where('is_active', true)
            ->with('operatorRole:id,code,acronym,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'profile_photo_path', 'specialty', 'role', 'operator_role_id']);

        return response()->json($operators->map(fn ($o) => [
            'id'           => $o->id,
            'name'         => $o->full_name,
            'role'         => $o->operatorRole?->name ?? $o->specialty ?? $o->role,
            'role_acronym' => $o->operatorRole?->short_label ?? null,
            'photo_url'    => $o->profile_photo_path
                ? Storage::disk('public')->url($o->profile_photo_path)
                : null,
        ]));
    }

    /** Panel de Equipo: estado en vivo (check-in) + carga de hoy por operador */
    public function team()
    {
        $operators = Operator::where('is_active', true)
            ->with('operatorRole:id,code,acronym,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'profile_photo_path', 'specialty', 'role', 'operator_role_id']);

        $operatorIds = $operators->pluck('id');

        $activeCheckins = OperatorCheckin::whereNull('checked_out_at')
            ->whereHas('user', fn ($q) => $q->whereIn('operator_id', $operatorIds))
            ->with(['branch:id,name', 'user:id,operator_id'])
            ->get()
            ->keyBy(fn (OperatorCheckin $c) => $c->user->operator_id);

        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $bookingsToday = SpaBooking::whereIn('operator_id', $operatorIds)
            ->whereBetween('scheduled_at', [$today, $tomorrow])
            ->whereIn('status', ['scheduled', 'work_order', 'completed'])
            ->with(['pet:id,name', 'services.service:id,name'])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy('operator_id');

        return response()->json($operators->map(function (Operator $o) use ($activeCheckins, $bookingsToday) {
            $checkin  = $activeCheckins->get($o->id);
            $bookings = $bookingsToday->get($o->id, collect());
            $current  = $bookings->firstWhere('status', 'work_order');

            return [
                'id'              => $o->id,
                'name'            => $o->full_name,
                'role'            => $o->operatorRole?->name ?? $o->specialty ?? $o->role,
                'photo_url'       => $o->profile_photo_path
                    ? Storage::disk('public')->url($o->profile_photo_path)
                    : null,
                'checked_in'      => (bool) $checkin,
                'checked_in_at'   => $checkin?->checked_in_at,
                'branch'          => $checkin?->branch
                    ? ['id' => $checkin->branch->id, 'name' => $checkin->branch->name]
                    : null,
                'pending_today'   => $bookings->whereIn('status', ['scheduled', 'work_order'])->count(),
                'completed_today' => $bookings->where('status', 'completed')->count(),
                'current_job'     => $current ? [
                    'booking_id'   => $current->id,
                    'pet_name'     => $current->pet?->name,
                    'service_name' => $current->services->first()?->service?->name,
                    'scheduled_at' => $current->scheduled_at,
                ] : null,
            ];
        }));
    }

    public function branches()
    {
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        return response()->json($branches);
    }
}
