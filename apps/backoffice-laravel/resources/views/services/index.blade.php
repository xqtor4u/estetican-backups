@php($screenDebugId = 'SerInd')
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <div class="d-flex gap-2">
            <a href="{{ route('services.create') }}" class="btn btn-primary">Crear servicio</a>
        </div>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
<section class="catalog-overview catalog-overview--services mb-4">
    <div class="catalog-overview__grid">
        <article class="catalog-overview-card catalog-overview-card--primary">
            <span class="catalog-overview-card__eyebrow">Panorama actual</span>
            <div class="catalog-overview-card__value">{{ $services->total() }}</div>
            <p class="catalog-overview-card__text">Servicios en catálogo con lectura rápida de estado, precio sugerido, duración y tipo de operador.</p>
        </article>
        <article class="catalog-overview-card">
            <span class="catalog-overview-card__eyebrow">Estado visible</span>
            <div class="catalog-overview-card__split">
                <div>
                    <div class="catalog-overview-card__value-sm">{{ $activeServicesCount }}</div>
                    <div class="catalog-overview-card__label">Activos en esta página</div>
                </div>
                <div>
                    <div class="catalog-overview-card__value-sm">{{ $inactiveServicesCount }}</div>
                    <div class="catalog-overview-card__label">Inactivos en esta página</div>
                </div>
            </div>
        </article>
        <article class="catalog-overview-card">
            <span class="catalog-overview-card__eyebrow">Carga operativa</span>
            <div class="catalog-overview-card__value-sm">{{ $historicUsageCount }}</div>
            <div class="catalog-overview-card__label">usos históricos visibles</div>
            <div class="catalog-overview-card__meta">Duración promedio sugerida: {{ $averageSuggestedMinutes }} min</div>
        </article>
    </div>
</section>

<x-list-filters :action="route('services.index')" :reset-url="route('services.index')">
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Buscar</label>
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Código, servicio, tipo o rol">
        <div class="form-text">Busca por nombre comercial, clave, categoría o rol operativo.</div>
    </div>
    <div class="col-lg-2 col-md-6">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select">
            <option value="all" @selected($status === 'all')>Todos</option>
            <option value="active" @selected($status === 'active')>Activos</option>
            <option value="inactive" @selected($status === 'inactive')>Inactivos</option>
        </select>
    </div>
    <div class="col-lg-4 col-md-12">
        <div class="catalog-filter-note">
            <span class="catalog-filter-note__kicker">Lectura rápida</span>
            <p class="catalog-filter-note__text mb-0">Cada fila prioriza servicio, tipo, perfil operativo, precio y adopción histórica para decidir sin abrir el detalle.</p>
        </div>
    </div>
</x-list-filters>

<x-list-table :paginator="$services">
    <thead>
        <tr>
            <th>Código</th>
            <th>
                <x-sortable-header-link route="services.index" column="name" label="Servicio" :sort="$sort" :direction="$direction" />
            </th>
            <th>
                <x-sortable-header-link route="services.index" column="type" label="Tipo" :sort="$sort" :direction="$direction" />
            </th>
            <th>Tipo de operador</th>
            <th>Precio sugerido</th>
            <th>Duración</th>
            <th>Estado</th>
            <th>Uso histórico</th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($services as $service)
            <tr class="catalog-row {{ $service->is_active ? '' : 'catalog-row--muted' }}">
                <td>
                    <div class="catalog-code-pill">{{ $service->code }}</div>
                </td>
                <td>
                    <div class="catalog-title-stack">
                        <div class="catalog-title-stack__title">{{ $service->name }}</div>
                        <div class="catalog-title-stack__description">{{ $service->description ? \Illuminate\Support\Str::limit($service->description, 110) : 'Sin descripción base.' }}</div>
                        <div class="catalog-inline-tags">
                            <span class="catalog-inline-tag">{{ strtoupper($service->type) }}</span>
                            <span class="catalog-inline-tag">{{ $service->suggested_duration_minutes }} min</span>
                            <span class="catalog-inline-tag">${{ number_format((float) $service->suggested_price, 2) }}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="catalog-type-pill">{{ strtoupper($service->type) }}</span>
                </td>
                <td>
                    @if($service->operatorRole)
                        <div class="catalog-role-stack">
                            <div class="catalog-role-stack__name">{{ $service->operatorRole->name }}</div>
                            <div class="catalog-role-stack__code">{{ $service->operatorRole->code }}</div>
                        </div>
                    @else
                        <span class="catalog-muted-copy">Sin tipo ligado</span>
                    @endif
                </td>
                <td>
                    <div class="catalog-stat">${{ number_format((float) $service->suggested_price, 2) }}</div>
                    <div class="catalog-stat__hint">precio sugerido</div>
                </td>
                <td>
                    <div class="catalog-stat">{{ $service->suggested_duration_minutes }}</div>
                    <div class="catalog-stat__hint">minutos</div>
                </td>
                <td>
                    <span class="catalog-status-badge {{ $service->is_active ? 'catalog-status-badge--active' : 'catalog-status-badge--inactive' }}">
                        {{ $service->is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td>
                    <div class="catalog-stat">{{ $service->executed_service_items_count }}</div>
                    <div class="catalog-stat__hint">movimientos</div>
                </td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        <a href="{{ route('services.show', $service) }}" class="btn btn-sm btn-outline-info">Ver</a>
                        <a href="{{ route('services.edit', $service) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form action="{{ route('services.duplicate', $service) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Duplicar</button>
                        </form>
                        <form action="{{ route('services.destroy', $service) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar servicio?">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="text-center py-4 text-body-secondary">Aún no hay servicios registrados.</td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>
</div>
@endsection