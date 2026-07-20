@php
    $screenDebugId = 'ArtInd';

    $page = \App\Support\Pages\ItemsPage::index();
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <form action="{{ route('items.catalog-sync') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Sincronizar catálogo WhatsApp</button>
        </form>
        <a href="{{ route('items.create') }}" class="btn btn-primary">Crear artículo</a>
    </x-slot:actions>
</x-page-header>

<x-list-filters :action="route('items.index')" :reset-url="route('items.index')">
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Buscar</label>
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Nombre, departamento, marca o presentación">
    </div>
    <div class="col-lg-2 col-md-6">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select">
            <option value="all" @selected($status === 'all')>Todos</option>
            <option value="active" @selected($status === 'active')>Activos</option>
            <option value="inactive" @selected($status === 'inactive')>Inactivos</option>
        </select>
    </div>
</x-list-filters>

<x-list-table :paginator="$items">
    <thead>
        <tr>
            <th></th>
            <th><x-sortable-header-link route="items.index" column="name" label="Nombre" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="items.index" column="department" label="Departamento" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="items.index" column="brand" label="Marca" :sort="$sort" :direction="$direction" /></th>
            <th>Presentación</th>
            <th>Estado</th>
            <th>Stock total</th>
            <th>Vacunas registradas</th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $item)
            <tr>
                <td style="width: 48px;">
                    @if($item->photo_path)
                        <img src="{{ $item->photo_thumbnail_url }}" alt="" class="rounded-3" style="width: 40px; height: 40px; object-fit: cover;">
                    @else
                        <div class="rounded-3 bg-light d-flex align-items-center justify-content-center text-body-secondary" style="width: 40px; height: 40px;">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    @endif
                </td>
                <td>
                    <div class="fw-semibold">{{ $item->name }}</div>
                    @if($item->notes)
                        <div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($item->notes, 80) }}</div>
                    @endif
                </td>
                <td>{{ $item->department ?: '—' }}</td>
                <td>{{ $item->brand ?: '—' }}</td>
                <td>{{ $item->presentation ?: '—' }}</td>
                <td>
                    <span class="badge {{ $item->is_active ? 'text-bg-success' : 'text-bg-danger' }} rounded-pill">
                        {{ $item->is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td class="{{ $item->stock_quantity < 0 ? 'text-danger' : '' }}">{{ $item->stock_quantity }}</td>
                <td>{{ $item->vaccinations_count }}</td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        <a href="{{ route('items.edit', $item) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form action="{{ route('items.destroy', $item) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar artículo?">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="py-5">
                    <x-empty-state
                        icon="bi-box-seam"
                        title="No hay artículos"
                        subtitle="El maestro de artículos está vacío o no hay resultados para tu búsqueda."
                        action-label="Crear artículo"
                        :action-route="route('items.create')"
                    />
                </td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>
@endsection
