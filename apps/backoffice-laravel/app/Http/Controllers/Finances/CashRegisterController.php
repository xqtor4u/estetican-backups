<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashRegister;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    public function index(): View
    {
        $registers = CashRegister::with(['branch', 'activeSession.openedBy'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('finances.cash-registers.index', compact('registers'));
    }

    public function create(): View
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('finances.cash-registers.create', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        CashRegister::create([
            'branch_id' => $validated['branch_id'],
            'name'      => trim($validated['name']),
            'is_active' => !empty($validated['is_active']),
        ]);

        return redirect()->route('finances.cash-registers.index')
            ->with('success', 'Caja creada.');
    }

    public function edit(CashRegister $cashRegister): View
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('finances.cash-registers.edit', compact('cashRegister', 'branches'));
    }

    public function update(Request $request, CashRegister $cashRegister): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $cashRegister->update([
            'branch_id' => $validated['branch_id'],
            'name'      => trim($validated['name']),
            'is_active' => !empty($validated['is_active']),
        ]);

        return redirect()->route('finances.cash-registers.index')
            ->with('success', 'Caja actualizada.');
    }

    public function destroy(CashRegister $cashRegister): RedirectResponse
    {
        if ($cashRegister->sessions()->exists()) {
            return redirect()->route('finances.cash-registers.index')
                ->with('error', 'No se puede eliminar una caja con sesiones registradas.');
        }

        $cashRegister->delete();

        return redirect()->route('finances.cash-registers.index')
            ->with('success', 'Caja eliminada.');
    }

    private function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'name'      => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
