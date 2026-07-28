<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $administrativeStatus = (string) $request->query('administrative_status', 'all');
        $resourceType = (string) $request->query('resource_type', 'all');
        $sort = (string) $request->query('sort', '');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (!in_array($administrativeStatus, ['all', 'active', 'inactive', 'retired'], true)) {
            $administrativeStatus = 'all';
        }

        $allowedResourceTypes = ['all', 'cage', 'room', 'equipment', 'other'];
        $allowedSorts = ['code', 'name', 'branch', 'status', 'allocations'];

        if (!in_array($resourceType, $allowedResourceTypes, true)) {
            $resourceType = 'all';
        }

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = '';
        }

        $resources = Resource::query()
            ->with('branch:id,name')
            ->withCount('allocations');

        if ($search !== '') {
            $resources->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('capacity_label', 'like', "%{$search}%")
                    ->orWhereHas('branch', function ($branchQuery) use ($search) {
                        $branchQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        if ($administrativeStatus !== 'all') {
            $resources->where('administrative_status', $administrativeStatus);
        }

        if ($resourceType !== 'all') {
            $resources->where('resource_type', $resourceType);
        }

        if ($sort === 'code') {
            $resources->orderBy('code', $direction)
                ->orderBy('branch_id')
                ->orderBy('name');
        } elseif ($sort === 'name') {
            $resources->orderBy('name', $direction)
                ->orderBy('branch_id')
                ->orderBy('code');
        } elseif ($sort === 'branch') {
            $resources->orderBy('branch_id', $direction)
                ->orderBy('code');
        } elseif ($sort === 'status') {
            $statusOrdering = $direction === 'asc'
                ? "case administrative_status when 'active' then 0 when 'inactive' then 1 else 2 end"
                : "case administrative_status when 'retired' then 0 when 'inactive' then 1 else 2 end";

            $resources->orderByRaw($statusOrdering)
                ->orderBy('operational_status', $direction)
                ->orderBy('code');
        } elseif ($sort === 'allocations') {
            $resources->orderBy('allocations_count', $direction)
                ->orderBy('code');
        } else {
            $resources->orderByRaw("case administrative_status when 'active' then 0 when 'inactive' then 1 else 2 end")
                ->orderBy('branch_id')
                ->orderBy('code');
        }

        $resources = $resources->paginate(15)->withQueryString();

        return view('resources.index', compact('resources', 'search', 'administrativeStatus', 'resourceType', 'sort', 'direction'));
    }

    public function create(Request $request): View
    {
        $copySourceId = (int) $request->query('copy_from');
        $copySource = $copySourceId ? Resource::with('branch')->find($copySourceId) : null;

        $branches = Branch::query()->orderBy('name')->get(['id', 'name', 'code']);
        $existingResources = Resource::orderBy('name')->get(['id', 'name', 'code', 'branch_id']);
        $returnTo = $request->query('return_to');

        return view('resources.create', compact('branches', 'existingResources', 'copySource', 'returnTo'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $resource = Resource::create($this->preparePayload($validated));

        return redirect()
            ->route('resources.index')
            ->with('success', 'Recurso creado.');
    }

    public function show(Resource $resource): View
    {
        $resource->load([
            'branch:id,name,code',
            'allocations' => fn ($query) => $query
                ->with(['pet:id,name', 'source'])
                ->orderByDesc('starts_at')
                ->limit(12),
            'photos' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderByDesc('taken_at')
                ->orderByDesc('created_at'),
            'events' => fn ($query) => $query
                ->with(['client:id,first_name,apellido_paterno,apellido_materno', 'pet:id,name', 'service:id,name,code'])
                ->withCount(['updates', 'photos'])
                ->orderByDesc('detected_at')
                ->orderByDesc('created_at')
                ->limit(12),
        ])->loadCount('allocations');

        $eventFormUsers = User::query()->orderBy('name')->get(['id', 'name']);
        $eventFormClients = Client::query()
            ->orderBy('first_name')
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->get(['id', 'first_name', 'apellido_paterno', 'apellido_materno']);
        $eventFormPets = Pet::query()
            ->visible()
            ->with('client:id,first_name,apellido_paterno,apellido_materno')
            ->orderBy('name')
            ->get(['id', 'client_id', 'name']);
        $eventFormServices = Service::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('resources.show', compact('resource', 'eventFormUsers', 'eventFormClients', 'eventFormPets', 'eventFormServices'));
    }

    public function edit(Resource $resource): View
    {
        $branches = Branch::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('resources.edit', compact('resource', 'branches'));
    }

    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $validated = $request->validate($this->rules($resource));

        $resource->update($this->preparePayload($validated));

        return redirect()->route('resources.edit', $resource)->with('success', 'Recurso actualizado.');
    }

    public function duplicate(Resource $resource): RedirectResponse
    {
        $duplicate = $resource->replicate();
        $duplicate->code = $this->buildDuplicateCode($resource);
        $duplicate->name = $this->buildDuplicateName($resource->name);
        $duplicate->administrative_status = 'inactive';
        $duplicate->save();

        return redirect()
            ->route('resources.edit', $duplicate)
            ->with('success', 'Recurso duplicado. Revisa clave, nombre y estado antes de activarlo.');
    }

    public function destroy(Resource $resource): RedirectResponse
    {
        if ($resource->allocations()->exists()) {
            return redirect()->route('resources.index')
                ->with('error', 'No se puede eliminar el recurso porque ya tiene asignaciones o bloqueos registrados.');
        }

        $resource->delete();

        return redirect()->route('resources.index')->with('success', 'Recurso eliminado.');
    }

    public function updateProfilePhoto(Request $request, Resource $resource)
    {
        $request->validate([
            'photo' => 'required|image|max:15360',
        ]);

        $imageManager = app(\App\Support\ResourcePhotoImageManager::class);
        $newPhotoPath = $imageManager->store($request->file('photo'));

        // Update the resource profile column
        $resource->update(['profile_photo_path' => $newPhotoPath]);

        // Also create a bitacora entry for traceability
        $resource->photos()->create([
            'photo_url' => $newPhotoPath,
            'photo_type' => 'perfil',
            'taken_at' => now(), // Resources usually don't have EXIF meaningful dates for profile
            'is_primary' => true,
        ]);

        // Mark other photos as not primary
        $resource->photos()->where('photo_url', '!=', $newPhotoPath)->update(['is_primary' => false]);

        return back()->with('success', 'Foto de perfil del recurso actualizada correctamente.');
    }

    private function rules(?Resource $resource = null): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'resource_type' => ['required', Rule::in(['cage', 'room', 'equipment', 'other'])],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('resources', 'code')
                    ->where(fn ($query) => $query->where('branch_id', request()->integer('branch_id')))
                    ->ignore($resource?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'capacity_label' => ['nullable', 'string', 'max:80'],
            'administrative_status' => ['required', Rule::in(['active', 'inactive', 'retired'])],
            'operational_status' => ['required', Rule::in(['available', 'occupied', 'cleaning', 'maintenance', 'blocked'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function preparePayload(array $validated): array
    {
        return [
            'branch_id' => (int) $validated['branch_id'],
            'resource_type' => $validated['resource_type'],
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'capacity_label' => $this->nullableString($validated['capacity_label'] ?? null),
            'administrative_status' => $validated['administrative_status'],
            'operational_status' => $validated['operational_status'],
            'notes' => $this->nullableString($validated['notes'] ?? null),
        ];
    }

    private function nullableString(?string $value): ?string
    {
        $normalized = is_string($value) ? trim($value) : '';

        return $normalized === '' ? null : $normalized;
    }

    private function buildDuplicateCode(Resource $resource): string
    {
        $baseCode = (string) Str::of($resource->code)
            ->upper()
            ->replaceMatches('/-COPY(?:-[0-9]+)?$/', '')
            ->toString();

        $candidate = Str::limit($baseCode . '-COPY', 60, '');
        $suffix = 2;

        while (Resource::query()
            ->where('branch_id', $resource->branch_id)
            ->where('code', $candidate)
            ->exists()) {
            $suffixLabel = '-COPY-' . $suffix;
            $candidate = Str::limit($baseCode, 60 - strlen($suffixLabel), '') . $suffixLabel;
            $suffix++;
        }

        return $candidate;
    }

    private function buildDuplicateName(string $name): string
    {
        $baseName = Str::of($name)->replaceLast(' (copia)', '')->toString();
        $candidate = $baseName . ' (copia)';
        $suffix = 2;

        while (Resource::query()->where('name', $candidate)->exists()) {
            $candidate = $baseName . ' (copia ' . $suffix . ')';
            $suffix++;
        }

        return $candidate;
    }
}