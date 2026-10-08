<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\NonWorkingDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ZEUS-047: catálogo de días inhábiles (festivos/cierres). Las series de citas recurrentes
 * recorren solas cualquier cita que caiga en uno de estos días.
 */
class NonWorkingDayController extends Controller
{
    public function index(Request $request): View
    {
        $showPast = $request->boolean('past');

        $days = NonWorkingDay::query()
            ->with('branch:id,name')
            ->when(! $showPast, fn ($q) => $q->whereDate('date', '>=', now()->toDateString()))
            ->orderBy('date')
            ->paginate(30)
            ->withQueryString();

        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('non-working-days.index', compact('days', 'branches', 'showPast'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
            'reason' => 'required|string|max:120',
        ]);

        $exists = NonWorkingDay::whereDate('date', $validated['date'])
            ->where('branch_id', $validated['branch_id'] ?? null)
            ->exists();
        if ($exists) {
            return back()->withInput()->with('error', 'Ese día ya está registrado como inhábil.');
        }

        NonWorkingDay::create($validated);

        return redirect()->route('non-working-days.index')->with('success', 'Día inhábil registrado.');
    }

    public function destroy(NonWorkingDay $nonWorkingDay): RedirectResponse
    {
        $nonWorkingDay->delete();

        return redirect()->route('non-working-days.index')->with('success', 'Día inhábil eliminado.');
    }
}
