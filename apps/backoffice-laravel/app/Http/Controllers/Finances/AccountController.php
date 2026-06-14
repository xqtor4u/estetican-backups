<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        // Carga todo el árbol en dos niveles de eager-loading
        $roots = Account::with(['children.children'])
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        $totals = [
            'total'   => Account::count(),
            'active'  => Account::where('is_active', true)->count(),
            'entries' => Account::where('allows_entries', true)->count(),
        ];

        return view('finances.accounts.index', compact('roots', 'totals'));
    }

    public function create(): View
    {
        $parents = Account::where('allows_entries', false)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return view('finances.accounts.create', compact('parents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        Account::create([
            'parent_id'      => $validated['parent_id'] ?: null,
            'code'           => strtoupper(trim($validated['code'])),
            'name'           => trim($validated['name']),
            'type'           => $validated['type'],
            'description'    => $validated['description'] ?: null,
            'is_active'      => !empty($validated['is_active']),
            'allows_entries' => !empty($validated['allows_entries']),
        ]);

        return redirect()->route('finances.accounts.index')
            ->with('success', 'Cuenta creada correctamente.');
    }

    public function edit(Account $account): View
    {
        $parents = Account::where('allows_entries', false)
            ->where('id', '!=', $account->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return view('finances.accounts.edit', compact('account', 'parents'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $validated = $request->validate($this->rules($account));

        $account->update([
            'parent_id'      => $validated['parent_id'] ?: null,
            'code'           => strtoupper(trim($validated['code'])),
            'name'           => trim($validated['name']),
            'type'           => $validated['type'],
            'description'    => $validated['description'] ?: null,
            'is_active'      => !empty($validated['is_active']),
            'allows_entries' => !empty($validated['allows_entries']),
        ]);

        return redirect()->route('finances.accounts.index')
            ->with('success', 'Cuenta actualizada.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->children()->exists()) {
            return redirect()->route('finances.accounts.index')
                ->with('error', 'No se puede eliminar una cuenta que tiene subcuentas.');
        }

        if ($account->journalEntryLines()->exists()) {
            return redirect()->route('finances.accounts.index')
                ->with('error', 'No se puede eliminar una cuenta con movimientos registrados.');
        }

        $account->delete();

        return redirect()->route('finances.accounts.index')
            ->with('success', 'Cuenta eliminada.');
    }

    private function rules(?Account $account = null): array
    {
        return [
            'parent_id'      => ['nullable', 'exists:accounts,id'],
            'code'           => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore($account?->id)],
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', Rule::in(Account::TYPES)],
            'description'    => ['nullable', 'string'],
            'is_active'      => ['nullable', 'boolean'],
            'allows_entries' => ['nullable', 'boolean'],
        ];
    }
}
