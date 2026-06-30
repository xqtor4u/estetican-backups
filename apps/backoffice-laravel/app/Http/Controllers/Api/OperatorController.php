<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Operator;
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

    public function branches()
    {
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        return response()->json($branches);
    }
}
