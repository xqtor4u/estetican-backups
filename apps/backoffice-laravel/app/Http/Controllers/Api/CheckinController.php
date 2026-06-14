<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\OperatorCheckin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckinController extends Controller
{
    /** Estado actual del usuario logueado */
    public function status()
    {
        $user    = Auth::user();
        $current = OperatorCheckin::where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->with('branch:id,name')
            ->latest('checked_in_at')
            ->first();

        if (! $current) {
            return response()->json(['checked_in' => false]);
        }

        return response()->json([
            'checked_in'     => true,
            'checkin_id'     => $current->id,
            'branch'         => ['id' => $current->branch->id, 'name' => $current->branch->name],
            'checked_in_at'  => $current->checked_in_at,
        ]);
    }

    /** Check-in en una sucursal */
    public function checkin(Request $request)
    {
        $request->validate(['branch_id' => 'required|exists:branches,id']);

        $user     = Auth::user();
        $branchId = $request->branch_id;

        // Buscar check-in abierto existente
        $open = OperatorCheckin::where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if ($open) {
            // Ya está en la misma sucursal — no hacer nada
            if ($open->branch_id === $branchId) {
                return response()->json([
                    'status'  => 'already',
                    'message' => 'Ya estás registrado en esta sucursal.',
                    'branch'  => Branch::find($branchId, ['id', 'name']),
                    'since'   => $open->checked_in_at,
                ]);
            }

            // Sucursal diferente → auto checkout con nota de transgresión
            $oldBranch = Branch::find($open->branch_id);
            $newBranch = Branch::find($branchId);
            $open->update([
                'checked_out_at'    => now(),
                'auto_checkout'     => true,
                'transgression_note' => "Check-out automático por check-in en \"{$newBranch->name}\" a las " . now()->format('H:i') . ". Check-in previo en \"{$oldBranch->name}\" desde {$open->checked_in_at->format('H:i')}.",
            ]);
        }

        $checkin = OperatorCheckin::create([
            'user_id'        => $user->id,
            'branch_id'      => $branchId,
            'checked_in_at'  => now(),
        ]);

        return response()->json([
            'status'        => 'ok',
            'checkin_id'    => $checkin->id,
            'branch'        => Branch::find($branchId, ['id', 'name']),
            'checked_in_at' => $checkin->checked_in_at,
            'transgression' => $open?->transgression_note,
        ], 201);
    }

    /** Check-out manual */
    public function checkout()
    {
        $user = Auth::user();
        $open = OperatorCheckin::where('user_id', $user->id)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if (! $open) {
            return response()->json(['message' => 'No hay check-in activo.'], 422);
        }

        $open->update(['checked_out_at' => now()]);

        return response()->json(['status' => 'ok', 'checked_out_at' => $open->checked_out_at]);
    }
}
