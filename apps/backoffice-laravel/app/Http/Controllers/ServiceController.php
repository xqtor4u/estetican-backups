<?php

namespace App\Http\Controllers;

use App\Models\GroupComponent;
use App\Models\OperatorRole;
use App\Models\Service;
use App\Support\CatalogCache\OperatorRoleCatalogCache;
use App\Support\Search\TokenSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
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

        if (!in_array($sort, ['name', 'type'], true)) {
            $sort = null;
        }

        $services = Service::query()
            ->select([
                'id',
                'code',
                'operator_role_id',
                'name',
                'description',
                'type',
                'suggested_price',
                'suggested_duration_minutes',
                'is_active',
            ])
            ->with('operatorRole:id,code,name')
            ->withCount('executedServiceItems')
            ->orderByDesc('is_active');

        if ($search !== '') {
            TokenSearch::apply($services, $search, [
                'code', 'name', 'type', 'description', 'operatorRole.name', 'operatorRole.code',
            ]);
        }

        if ($status === 'active') {
            $services->where('is_active', true);
        } elseif ($status === 'inactive') {
            $services->where('is_active', false);
        }

        if ($sort) {
            $services->orderBy($sort, $direction)
                ->orderBy($sort === 'name' ? 'type' : 'name');
        } else {
            $services->orderBy('type')
                ->orderBy('name');
        }

        $services = $services->paginate(15)->withQueryString();

        $page = \App\Support\Pages\ServicesPage::index();
        $serviceCollection = $services->getCollection();
        $activeServicesCount = $serviceCollection->where('is_active', true)->count();
        $inactiveServicesCount = $serviceCollection->count() - $activeServicesCount;
        $historicUsageCount = $serviceCollection->sum('executed_service_items_count');
        $averageSuggestedMinutes = (int) round($serviceCollection->avg('suggested_duration_minutes') ?? 0);

        return view('services.index', compact(
            'services', 'sort', 'direction', 'search', 'status', 'page',
            'activeServicesCount', 'inactiveServicesCount', 'historicUsageCount', 'averageSuggestedMinutes'
        ));
    }

    public function create(Request $request): View
    {
        $copySourceId = (int) $request->query('copy_from');
        $copySource = $copySourceId ? Service::with('operatorRole')->find($copySourceId) : null;

        $operatorRoles = OperatorRoleCatalogCache::activeForForms();
        $existingServices = Service::orderBy('name')->get(['id', 'name', 'code']);

        return view('services.create', compact('operatorRoles', 'existingServices', 'copySource'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        Service::create($this->preparePayload($validated));

        return redirect()->route('services.index')->with('success', 'Servicio creado.');
    }

    public function show(Service $service): View
    {
        $service->load(['operatorRole'])->loadCount('executedServiceItems');

        return view('services.show', compact('service'));
    }

    public function edit(Service $service): View
    {
        $operatorRoles = OperatorRoleCatalogCache::activeForService($service->operator_role_id);

        return view('services.edit', compact('service', 'operatorRoles'));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate($this->rules($service));

        $service->update($this->preparePayload($validated, $service));

        return redirect()->route('services.edit', $service)->with('success', 'Servicio actualizado.');
    }

    public function duplicate(Service $service): RedirectResponse
    {
        $duplicate = $service->replicate();
        $duplicate->code = $this->generateServiceCode($service->type);
        $duplicate->name = $this->buildDuplicateName($service->name);
        $duplicate->is_active = false;
        $duplicate->save();

        return redirect()->route('services.edit', $duplicate)->with('success', 'Servicio duplicado. Revisa código, nombre y detalles antes de activarlo.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $groupCount = GroupComponent::where('service_id', $service->id)->count();

        if ($groupCount > 0) {
            return redirect()->route('services.index')
                ->with('error', "No se puede eliminar: es componente de {$groupCount} grupo(s). Quítalo del/de los grupo(s) primero.");
        }

        $service->delete();

        return redirect()->route('services.index')->with('success', 'Servicio eliminado.');
    }

    private function rules(?Service $service = null): array
    {
        return [
            'code' => ['nullable', 'string', 'max:255', Rule::unique('services', 'code')->ignore($service?->id)],
            'operator_role_id' => ['required', 'integer', Rule::exists('operator_roles', 'id')],
            'type' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'suggested_price' => 'required|numeric|min:0',
            'suggested_duration_minutes' => 'required|integer|min:0',
            'recurrence_days' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'is_core_vaccine' => 'nullable|boolean',
            'ai_visible' => 'nullable|boolean',
            'is_generic' => 'nullable|boolean',
            'is_emergency' => 'nullable|boolean',
        ];
    }

    private function preparePayload(array $validated, ?Service $service = null): array
    {
        $suggestedPrice = number_format((float) $validated['suggested_price'], 2, '.', '');
        $suggestedDuration = (int) $validated['suggested_duration_minutes'];
        $code = $validated['code'] ?? null;

        if ($code) {
            $code = $this->normalizeServiceCode($code);
        }

        if (!$code) {
            $code = $service?->code ?? $this->generateServiceCode($validated['type']);
        }

        return [
            'code' => $code,
            'operator_role_id' => (int) $validated['operator_role_id'],
            'type' => $validated['type'],
            'department' => $validated['department'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $suggestedPrice,
            'suggested_price' => $suggestedPrice,
            'duration_minutes' => $suggestedDuration,
            'suggested_duration_minutes' => $suggestedDuration,
            'recurrence_days' => $validated['recurrence_days'] ?? null,
            'is_active' => !empty($validated['is_active']),
            'is_core_vaccine' => !empty($validated['is_core_vaccine']),
            'ai_visible' => !empty($validated['ai_visible']),
            'is_generic' => !empty($validated['is_generic']),
            'is_emergency' => !empty($validated['is_emergency']),
        ];
    }

    private function normalizeServiceCode(string $code): string
    {
        return (string) Str::of($code)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-');
    }

    private function generateServiceCode(string $type): string
    {
        $prefix = match ($type) {
            'spa' => 'SPA',
            'hotel' => 'HOT',
            'extra' => 'EXT',
            'combo' => 'COM',
            default => 'SRV',
        };

        $sequence = 1;

        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence);
            $sequence++;
        } while (Service::query()->where('code', $candidate)->exists());

        return $candidate;
    }

    private function buildDuplicateName(string $name): string
    {
        $baseName = Str::of($name)->replaceLast(' (copia)', '')->toString();
        $candidate = $baseName . ' (copia)';
        $suffix = 2;

        while (Service::query()->where('name', $candidate)->exists()) {
            $candidate = $baseName . ' (copia ' . $suffix . ')';
            $suffix++;
        }

        return $candidate;
    }
}