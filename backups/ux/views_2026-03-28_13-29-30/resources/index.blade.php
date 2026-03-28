@extends('layouts.app')

@php
    use App\Support\Pages\ResourcesPage;
    use Illuminate\Support\Str;

    $page = ResourcesPage::index();
    $breadcrumbs = $page['breadcrumbs'];
    $resourceCollection = $resources->getCollection();
    $activeResourcesCount = $resourceCollection->where('administrative_status', 'active')->count();
    $inactiveResourcesCount = $resourceCollection->where('administrative_status', 'inactive')->count();
    $retiredResourcesCount = $resourceCollection->where('administrative_status', 'retired')->count();
    $allocationsVisibleCount = (int) $resourceCollection->sum('allocations_count');
    $cageResourcesCount = $resourceCollection->where('resource_type', 'cage')->count();
@endphp

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('resources.create') }}" class="btn btn-primary">Crear recurso</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
<section class="catalog-overview mb-4">
    <div class="catalog-overview__grid">
        <article class="catalog-overview-card catalog-overview-card--primary">
            <span class="catalog-overview-card__eyebrow">Panorama actual</span>
            <div class="catalog-overview-card__value">{{ $resources->total() }}</div>
            <p class="catalog-overview-card__text">Activos físicos por sucursal para agenda, bloqueos, limpieza y disponibilidad compartida.</p>
        </article>
        <article class="catalog-overview-card">
            <span class="catalog-overview-card__eyebrow">Estado visible</span>
            <div class="catalog-overview-card__split">
                <div>
                    <div class="catalog-overview-card__value-sm">{{ $activeResourcesCount }}</div>
                    <div class="catalog-overview-card__label">Activos</div>
                </div>
                <div>
                    <div class="catalog-overview-card__value-sm">{{ $inactiveResourcesCount }}</div>
                    <div class="catalog-overview-card__label">Inactivos</div>
                </div>
                <div>
                    <div class="catalog-overview-card__value-sm">{{ $retiredResourcesCount }}</div>
                    <div class="catalog-overview-card__label">Retirados</div>
                </div>
            </div>
        </article>
        <article class="catalog-overview-card">
            <span class="catalog-overview-card__eyebrow">Carga visible</span>
            <div class="catalog-overview-card__value-sm">{{ $allocationsVisibleCount }}</div>
            <div class="catalog-overview-card__label">asignaciones listadas</div>
            <div class="catalog-overview-card__meta">Jaulas visibles en esta página: {{ $cageResourcesCount }}</div>
        </article>
    </div>
</section>

<x-list-filters :action="route('resources.index')" :reset-url="route('resources.index')">
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Buscar</label>
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Clave, recurso, capacidad o sucursal">
        <div class="form-text">Busca por clave, nombre operativo, clasificación, capacidad o sucursal.</div>
    </div>
    <div class="col-lg-3 col-md-6">
        <label class="form-label">Estado administrativo</label>
        <select name="administrative_status" class="form-select">
            <option value="all" @selected($administrativeStatus === 'all')>Todos</option>
            <option value="active" @selected($administrativeStatus === 'active')>Activos</option>
            <option value="inactive" @selected($administrativeStatus === 'inactive')>Inactivos</option>
            <option value="retired" @selected($administrativeStatus === 'retired')>Retirados</option>
        </select>
    </div>
    <div class="col-lg-3 col-md-6">
        <label class="form-label">Tipo de recurso</label>
        <select name="resource_type" class="form-select">
            <option value="all" @selected($resourceType === 'all')>Todos</option>
            <option value="cage" @selected($resourceType === 'cage')>Jaula</option>
            <option value="room" @selected($resourceType === 'room')>Espacio</option>
            <option value="equipment" @selected($resourceType === 'equipment')>Equipo</option>
            <option value="other" @selected($resourceType === 'other')>Otro</option>
        </select>
    </div>
    <div class="col-lg-2 col-md-12">
        <div class="catalog-filter-note">
            <span class="catalog-filter-note__kicker">Lectura rápida</span>
            <p class="catalog-filter-note__text mb-0">Ordena por clave, sucursal, estado o uso para detectar rápido qué activo conviene clonar, activar o depurar.</p>
        </div>
    </div>
</x-list-filters>

<x-list-table :paginator="$resources">
    <thead>
        <tr>
            <th><x-sortable-header-link route="resources.index" column="code" label="Clave" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="resources.index" column="name" label="Recurso" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="resources.index" column="branch" label="Sucursal" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="resources.index" column="status" label="Estado" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="resources.index" column="allocations" label="Asignaciones" :sort="$sort" :direction="$direction" /></th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($resources as $resource)
            <tr class="catalog-row {{ $resource->administrative_status === 'active' ? '' : 'catalog-row--muted' }}">
                <td>
                    <div class="catalog-code-pill">{{ $resource->code }}</div>
                </td>
                <td>
                    <div class="catalog-title-stack">
                        <div class="catalog-title-stack__title">{{ $resource->name }}</div>
                        <div class="catalog-title-stack__description">{{ $resource->notes ? Str::limit($resource->notes, 110) : 'Sin nota operativa capturada.' }}</div>
                        <div class="catalog-inline-tags">
                            <span class="catalog-inline-tag">{{ strtoupper($resource->resource_type) }}</span>
                            <span class="catalog-inline-tag">{{ $resource->capacity_label ?: 'Sin tamaño' }}</span>
                            <span class="catalog-inline-tag">{{ ucfirst($resource->operational_status) }}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="fw-semibold">{{ $resource->branch?->name ?: 'Sin sucursal' }}</div>
                    <div class="text-body-secondary small">{{ $resource->branch?->code ?: 'Sin clave de sede' }}</div>
                </td>
                <td>
                    <span class="catalog-status-badge {{ $resource->administrative_status === 'active' ? 'catalog-status-badge--active' : 'catalog-status-badge--inactive' }}">
                        {{ ucfirst($resource->administrative_status) }}
                    </span>
                    <div class="catalog-stat__hint mt-1">Operativo: {{ ucfirst($resource->operational_status) }}</div>
                </td>
                <td>
                    <div class="catalog-stat">{{ $resource->allocations_count }}</div>
                    <div class="catalog-stat__hint">bloqueos y usos</div>
                </td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        <a href="{{ route('resources.show', $resource) }}" class="btn btn-sm btn-outline-info">Ver</a>
                        <a href="{{ route('resources.edit', $resource) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form action="{{ route('resources.duplicate', $resource) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Duplicar</button>
                        </form>
                        <form action="{{ route('resources.destroy', $resource) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar recurso? Esta acción no se puede deshacer.">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center py-4 text-body-secondary">Aún no hay recursos registrados.</td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>
</div>
@endsection