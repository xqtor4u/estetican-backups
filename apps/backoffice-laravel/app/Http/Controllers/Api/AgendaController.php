<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpaBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AgendaController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $operatorId = $request->query('operator_id');

        $bookings = SpaBooking::whereDate('scheduled_at', $date)
            ->whereNotIn('status', ['cancelled'])
            ->when($operatorId, fn ($q) => $q->where('operator_id', $operatorId))
            ->with([
                'pet:id,name,species,breed,profile_photo_path,client_id',
                'pet.client:id,first_name,last_name',
                'services.service:id,name,type',
                'quotes' => fn ($q) => $q->where('status', 'accepted')
                    ->with(['items.operator:id,full_name,profile_photo_path']),
            ])
            ->orderBy('scheduled_at')
            ->get();

        return response()->json($bookings->map(function (SpaBooking $b) {
            // Operadores únicos del presupuesto aceptado
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
                    ->unique('id')
                    ->values();
            }

            $endTime = $b->duration_minutes
                ? $b->scheduled_at->copy()->addMinutes($b->duration_minutes)->format('H:i')
                : null;

            return [
                'id' => $b->id,
                'scheduled_at' => $b->scheduled_at,
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

    /** Citas abiertas de días anteriores sin resolver (scheduled/work_order) */
    public function vencidas()
    {
        $bookings = SpaBooking::whereIn('status', ['scheduled', 'work_order'])
            ->where('scheduled_at', '<', now()->startOfDay())
            ->with([
                'pet:id,name,species,breed,profile_photo_path,client_id',
                'pet.client:id,first_name,last_name',
                'services.service:id,name,type',
            ])
            ->orderBy('scheduled_at')
            ->get();

        return response()->json($bookings->map(function (SpaBooking $b) {
            $endTime = $b->duration_minutes
                ? $b->scheduled_at->copy()->addMinutes($b->duration_minutes)->format('H:i')
                : null;

            return [
                'id' => $b->id,
                'scheduled_at' => $b->scheduled_at,
                'time' => $b->scheduled_at->format('H:i'),
                'date_label' => $b->scheduled_at->translatedFormat('D j M'),
                'end_time' => $endTime,
                'status' => $b->status,
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
