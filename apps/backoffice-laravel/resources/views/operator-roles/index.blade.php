@php
    $screenDebugId = 'OprRolInd';
    use App\Support\Pages\OperatorRolesPage;

    $page = OperatorRolesPage::index();
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
        <a href="{{ route('operator-roles.create') }}" class="btn btn-primary">Crear tipo</a>
    </x-slot:actions>
</x-page-header>

<x-list-filters :action="route('operator-roles.index')" :reset-url="route('operator-roles.index')">
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Buscar</label>
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Clave, tipo o descripción">
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

<x-list-table :paginator="$operatorRoles">
    <thead>
        <tr>
            <th><x-sortable-header-link route="operator-roles.index" column="code" label="Clave" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="operator-roles.index" column="name" label="Tipo" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="operator-roles.index" column="rate" label="Costo base/hora" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="operator-roles.index" column="status" label="Estado" :sort="$sort" :direction="$direction" /></th>
            <th>Acceso</th>
            <th><x-sortable-header-link route="operator-roles.index" column="assignments" label="Asignaciones" :sort="$sort" :direction="$direction" /></th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($operatorRoles as $operatorRole)
            <tr>
                <td><span class="badge text-bg-dark">{{ $operatorRole->code }}</span></td>
                <td>
                    <div class="fw-semibold">{{ $operatorRole->name }}</div>
                    <div class="small text-body-secondary">{{ $operatorRole->description ?: 'Sin descripción.' }}</div>
                </td>
                <td>
                    @if($operatorRole->default_hourly_rate !== null)
                        ${{ number_format((float) $operatorRole->default_hourly_rate, 2) }}
                    @else
                        Sin base
                    @endif
                </td>
                <td>
                    <span class="badge {{ $operatorRole->is_active ? 'text-bg-success' : 'text-bg-danger' }} rounded-pill">
                        {{ $operatorRole->is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td>
                    @if($operatorRole->can_login)
                        <span class="badge text-bg-primary">Permitido</span>
                    @else
                        <span class="badge text-bg-light text-muted">Denegado</span>
                    @endif
                </td>
                <td>{{ $operatorRole->assignments_count }}</td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        <a href="{{ route('operator-roles.show', $operatorRole) }}" class="btn btn-sm btn-outline-info">Ver</a>
                        <a href="{{ route('operator-roles.edit', $operatorRole) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form action="{{ route('operator-roles.duplicate', $operatorRole) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Duplicar</button>
                        </form>
                        <form action="{{ route('operator-roles.destroy', $operatorRole) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Inactivar tipo de operador?">Inactivar</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="py-5">
                    <x-empty-state 
                        icon="bi-briefcase-fill"
                        title="No hay tipos de operador"
                        subtitle="El catálogo de roles técnicos está vacío o no hay resultados para tu búsqueda."
                        action-label="Crear nuevo tipo"
                        :action-route="route('operator-roles.create')"
                    />
                </td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>
@endsection