@php
    $screenDebugId = 'BraInd';
    use App\Support\Pages\BranchesPage;

    $page = BranchesPage::index();
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
        <a href="{{ route('branches.create') }}" class="btn btn-primary">Crear sucursal</a>
    </x-slot:actions>
</x-page-header>

<x-list-filters :action="route('branches.index')" :reset-url="route('branches.index')">
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Buscar</label>
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Clave, sucursal o dirección">
    </div>
    <div class="col-lg-2 col-md-6">
        <label class="form-label">Estado</label>
        <select name="status" class="form-select">
            <option value="all" @selected($status === 'all')>Todas</option>
            <option value="active" @selected($status === 'active')>Activas</option>
            <option value="inactive" @selected($status === 'inactive')>Inactivas</option>
        </select>
    </div>
</x-list-filters>

<x-list-table :paginator="$branches">
    <thead>
        <tr>
            <th><x-sortable-header-link route="branches.index" column="code" label="Clave" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="branches.index" column="name" label="Sucursal" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="branches.index" column="status" label="Estado" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="branches.index" column="assignments" label="Asignaciones" :sort="$sort" :direction="$direction" /></th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($branches as $branch)
            <tr>
                <td><span class="badge text-bg-dark">{{ $branch->code }}</span></td>
                <td>
                    <div class="fw-semibold">{{ $branch->name }}</div>
                    <div class="small text-body-secondary">{{ $branch->formatted_address !== '' ? $branch->formatted_address : ($branch->notes ?: 'Sin dirección capturada.') }}</div>
                </td>
                <td>
                    <span class="badge {{ $branch->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $branch->is_active ? 'Activa' : 'Inactiva' }}
                    </span>
                </td>
                <td>{{ $branch->operator_assignments_count }}</td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        <a href="{{ route('branches.show', $branch) }}" class="btn btn-sm btn-outline-info">Ver</a>
                        <a href="{{ route('branches.edit', $branch) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form action="{{ route('branches.destroy', $branch) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar sucursal? Esta acción no se puede deshacer.">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center py-4 text-body-secondary">Aún no hay sucursales registradas.</td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>
@endsection