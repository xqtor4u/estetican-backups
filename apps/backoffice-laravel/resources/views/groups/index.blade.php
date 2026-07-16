@php
    $screenDebugId = 'GrpInd';

    $page = \App\Support\Pages\GroupsPage::index();
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
        <a href="{{ route('groups.create') }}" class="btn btn-primary">Crear grupo</a>
    </x-slot:actions>
</x-page-header>

<x-list-filters :action="route('groups.index')" :reset-url="route('groups.index')">
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Buscar</label>
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Nombre del grupo">
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

<x-list-table :paginator="$groups">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Componentes</th>
            <th>Precio</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($groups as $group)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $group->name }}</div>
                    @if($group->description)
                        <div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($group->description, 80) }}</div>
                    @endif
                </td>
                <td>{{ $group->components_count }}</td>
                <td>${{ number_format($group->calculatedPrice(), 2) }}</td>
                <td>
                    <span class="badge {{ $group->is_active ? 'text-bg-success' : 'text-bg-danger' }} rounded-pill">
                        {{ $group->is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        <a href="{{ route('groups.edit', $group) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form action="{{ route('groups.destroy', $group) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar grupo?">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="py-5">
                    <x-empty-state
                        icon="bi-collection"
                        title="No hay grupos"
                        subtitle="El catálogo de grupos está vacío o no hay resultados para tu búsqueda."
                        action-label="Crear grupo"
                        :action-route="route('groups.create')"
                    />
                </td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>
@endsection
