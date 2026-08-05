<?php

namespace App\Http\Controllers;

use App\Models\OperatorRole;
use App\Support\Search\TokenSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OperatorRoleController extends Controller
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

        if (!in_array($sort, ['code', 'name', 'rate', 'assignments', 'status'], true)) {
            $sort = null;
        }

        $operatorRoles = OperatorRole::query()
            ->withCount('assignments');

        if ($search !== '') {
            TokenSearch::apply($operatorRoles, $search, ['code', 'name', 'description']);
        }

        if ($status === 'active') {
            $operatorRoles->where('is_active', true);
        } elseif ($status === 'inactive') {
            $operatorRoles->where('is_active', false);
        }

        if ($sort === 'code') {
            $operatorRoles->orderBy('code', $direction)->orderBy('name');
        } elseif ($sort === 'name') {
            $operatorRoles->orderBy('name', $direction)->orderBy('code');
        } elseif ($sort === 'rate') {
            $operatorRoles->orderBy('default_hourly_rate', $direction)->orderBy('name');
        } elseif ($sort === 'assignments') {
            $operatorRoles->orderBy('assignments_count', $direction)->orderBy('name');
        } elseif ($sort === 'status') {
            $operatorRoles->orderBy('is_active', $direction)->orderBy('name');
        } else {
            $operatorRoles->orderByDesc('is_active')->orderBy('name');
        }

        $operatorRoles = $operatorRoles
            ->paginate(15);

        return view('operator-roles.index', compact('operatorRoles', 'search', 'status', 'sort', 'direction'));
    }

    public function create(Request $request): View
    {
        $copySourceId = (int) $request->query('copy_from');
        $copySource = $copySourceId ? OperatorRole::find($copySourceId) : null;
        
        $existingRoles = OperatorRole::orderBy('name')->get(['id', 'name', 'code']);
        $returnTo = $request->query('return_to');

        return view('operator-roles.create', compact('returnTo', 'existingRoles', 'copySource'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        OperatorRole::create($this->preparePayload($validated));

        $returnTo = $this->sanitizeReturnTo($request->input('return_to'));

        if ($returnTo) {
            return redirect($returnTo)->with('success', 'Tipo de operador creado.');
        }

        return redirect()->route('operator-roles.index')->with('success', 'Tipo de operador creado.');
    }

    public function show(OperatorRole $operatorRole): View
    {
        $operatorRole->loadCount('assignments');

        return view('operator-roles.show', compact('operatorRole'));
    }

    public function edit(OperatorRole $operatorRole): View
    {
        return view('operator-roles.edit', compact('operatorRole'));
    }

    public function update(Request $request, OperatorRole $operatorRole): RedirectResponse
    {
        $validated = $request->validate($this->rules($operatorRole));

        $operatorRole->update($this->preparePayload($validated));

        return redirect()->route('operator-roles.edit', $operatorRole)->with('success', 'Tipo de operador actualizado.');
    }

    public function duplicate(OperatorRole $operatorRole): RedirectResponse
    {
        $duplicate = $operatorRole->replicate();
        $duplicate->code = $this->buildDuplicateCode($operatorRole->code);
        $duplicate->name = $this->buildDuplicateName($operatorRole->name);
        $duplicate->is_active = false;
        $duplicate->save();

        return redirect()->route('operator-roles.edit', $duplicate)->with('success', 'Tipo de operador duplicado. Revisa clave, nombre y detalles antes de activarlo.');
    }

    private function buildDuplicateCode(string $code): string
    {
        $baseCode = strtoupper(trim($code));
        $candidate = $baseCode . '-COPY';
        $suffix = 2;

        while (OperatorRole::where('code', $candidate)->exists()) {
            $candidate = $baseCode . '-COPY-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function buildDuplicateName(string $name): string
    {
        $baseName = Str::of($name)->replaceLast(' (copia)', '')->toString();
        $candidate = $baseName . ' (copia)';
        $suffix = 2;

        while (OperatorRole::where('name', $candidate)->exists()) {
            $candidate = $baseName . ' (copia ' . $suffix . ')';
            $suffix++;
        }

        return $candidate;
    }

    public function destroy(OperatorRole $operatorRole): RedirectResponse
    {
        $operatorRole->update(['is_active' => false]);

        return redirect()->route('operator-roles.index')->with('success', 'Tipo de operador inactivado.');
    }

    private function rules(?OperatorRole $operatorRole = null): array
    {
        return [
            'code'     => ['required', 'string', 'max:255', Rule::unique('operator_roles', 'code')->ignore($operatorRole?->id)],
            'acronym'  => ['nullable', 'string', 'size:3', 'regex:/^[A-Z0-9]{3}$/', Rule::unique('operator_roles', 'acronym')->ignore($operatorRole?->id)],
            'name'     => ['required', 'string', 'max:255', Rule::unique('operator_roles', 'name')->ignore($operatorRole?->id)],
            'description' => 'nullable|string',
            'default_hourly_rate' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'can_login' => 'nullable|boolean',
        ];
    }

    private function preparePayload(array $validated): array
    {
        return [
            'code'    => strtoupper(trim($validated['code'])),
            'acronym' => isset($validated['acronym']) && $validated['acronym'] !== ''
                ? strtoupper(trim($validated['acronym']))
                : null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'notes' => $validated['description'] ?? null,
            'default_hourly_rate' => isset($validated['default_hourly_rate']) && $validated['default_hourly_rate'] !== null && $validated['default_hourly_rate'] !== ''
                ? number_format((float) $validated['default_hourly_rate'], 2, '.', '')
                : null,
            'is_active' => !empty($validated['is_active']),
            'can_login' => !empty($validated['can_login']),
        ];
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
}