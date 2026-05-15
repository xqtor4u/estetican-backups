<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        if (!in_array($sort, ['code', 'name', 'assignments', 'status'], true)) {
            $sort = null;
        }

        $branches = Branch::query()
            ->withCount('operatorAssignments');

        if ($search !== '') {
            $branches->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('street', 'like', "%{$search}%")
                    ->orWhere('colonia', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $branches->where('is_active', true);
        } elseif ($status === 'inactive') {
            $branches->where('is_active', false);
        }

        if ($sort === 'code') {
            $branches->orderBy('code', $direction)->orderBy('name');
        } elseif ($sort === 'name') {
            $branches->orderBy('name', $direction)->orderBy('code');
        } elseif ($sort === 'assignments') {
            $branches->orderBy('operator_assignments_count', $direction)->orderBy('name');
        } elseif ($sort === 'status') {
            $branches->orderBy('is_active', $direction)->orderBy('name');
        } else {
            $branches->orderByDesc('is_active')->orderBy('name');
        }

        $branches = $branches
            ->paginate(15);

        return view('branches.index', compact('branches', 'search', 'status', 'sort', 'direction'));
    }

    public function create(): View
    {
        $returnTo = request()->query('return_to');

        return view('branches.create', compact('returnTo'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $branch = Branch::create($this->preparePayload($validated));
        $returnTo = $this->sanitizeReturnTo($request->input('return_to'));

        if ($returnTo) {
            return redirect($this->appendBranchId($returnTo, $branch->id))
                ->with('success', 'Sucursal creada.');
        }

        return redirect()->route('branches.index')->with('success', 'Sucursal creada.');
    }

    public function show(Branch $branch): View
    {
        $branch->load(['operatorAssignments.operator'])->loadCount('operatorAssignments');

        return view('branches.show', compact('branch'));
    }

    public function edit(Branch $branch): View
    {
        return view('branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate($this->rules($branch));

        $branch->update($this->preparePayload($validated));

        return redirect()->route('branches.edit', $branch)->with('success', 'Sucursal actualizada.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        if ($branch->operatorAssignments()->exists()) {
            return redirect()->route('branches.index')
                ->with('error', 'No se puede eliminar la sucursal porque todavía tiene operadores asignados.');
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Sucursal eliminada.');
    }

    private function rules(?Branch $branch = null): array
    {
        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->ignore($branch?->id)],
            'street' => 'nullable|string|max:255',
            'exterior_number' => 'nullable|string|max:255',
            'interior_number' => 'nullable|string|max:255',
            'colonia' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    private function preparePayload(array $validated): array
    {
        return [
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'street' => $this->nullableString($validated['street'] ?? null),
            'exterior_number' => $this->nullableString($validated['exterior_number'] ?? null),
            'interior_number' => $this->nullableString($validated['interior_number'] ?? null),
            'colonia' => $this->nullableString($validated['colonia'] ?? null),
            'city' => $this->nullableString($validated['city'] ?? null),
            'state' => $this->nullableString($validated['state'] ?? null),
            'zip' => $this->nullableString($validated['zip'] ?? null),
            'country' => $this->nullableString($validated['country'] ?? null) ?? 'México',
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => !empty($validated['is_active']),
        ];
    }

    private function nullableString(?string $value): ?string
    {
        $normalized = is_string($value) ? trim($value) : '';

        return $normalized === '' ? null : $normalized;
    }

    private function sanitizeReturnTo(?string $returnTo): ?string
    {
        if (!$returnTo) {
            return null;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && Str::startsWith($returnTo, $appUrl . '/')) {
            return $returnTo;
        }

        return Str::startsWith($returnTo, '/') ? $returnTo : null;
    }

    private function appendBranchId(string $returnTo, int $branchId): string
    {
        $separator = str_contains($returnTo, '?') ? '&' : '?';

        return $returnTo . $separator . 'branch_id=' . $branchId;
    }
}