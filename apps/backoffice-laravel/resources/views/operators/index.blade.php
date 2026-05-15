@php
    $screenDebugId = 'OpeInd';
    use App\Support\Pages\OperatorsPage;

    $page = OperatorsPage::index();
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
        <a href="{{ route('operators.create') }}" class="btn btn-primary">Crear operador</a>
    </x-slot:actions>
</x-page-header>

<x-list-filters :action="route('operators.index')" :reset-url="route('operators.index')">
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Buscar</label>
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Operador, clave, teléfono, rol o sucursal">
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

<x-list-table :paginator="$operators">
    <thead>
        <tr>
            <th><x-sortable-header-link route="operators.index" column="name" label="Operador" :sort="$sort" :direction="$direction" /></th>
            <th>Teléfono</th>
            <th>Roles</th>
            <th>Base</th>
            <th>Pago/hora</th>
            <th><x-sortable-header-link route="operators.index" column="status" label="Estado" :sort="$sort" :direction="$direction" /></th>
            <th><x-sortable-header-link route="operators.index" column="jobs" label="Trabajos registrados" :sort="$sort" :direction="$direction" /></th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($operators as $operator)
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        @if($operator->profile_photo_thumbnail_url)
                            <img src="{{ $operator->profile_photo_thumbnail_url }}" alt="Foto de {{ $operator->full_name ?: $operator->name }}" class="rounded-circle border app-avatar-xs app-media-cover">
                        @else
                            <div class="rounded-circle border d-flex align-items-center justify-content-center text-body-secondary bg-body-tertiary app-avatar-xs app-avatar-placeholder">
                                {{ \Illuminate\Support\Str::of($operator->full_name ?: $operator->name)->trim()->substr(0, 1)->upper() }}
                            </div>
                        @endif

                        <div>
                            <div class="fw-semibold">{{ $operator->full_name ?: $operator->name }}</div>
                            <div class="text-body-secondary small">Clave {{ $operator->code }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $operator->phone ?: 'Sin teléfono' }}</td>
                <td>
                    @if($operator->roles->isNotEmpty())
                        {{ $operator->roles->pluck('name')->implode(', ') }}
                    @else
                        {{ $operator->role ?: 'Sin roles definidos' }}
                    @endif
                </td>
                <td>{{ optional($operator->primaryBranch())->name ?: 'Sin base' }}</td>
                <td>
                    @if($operator->effectiveHourlyRate() !== null)
                        ${{ number_format($operator->effectiveHourlyRate(), 2) }}
                    @else
                        Sin tarifa
                    @endif
                </td>
                <td>
                    <span class="badge {{ $operator->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $operator->is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </td>
                <td>{{ $operator->executed_services_count }}</td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        <a href="{{ route('operators.show', $operator) }}" class="btn btn-sm btn-outline-info">Ver</a>
                        <a href="{{ route('operators.edit', $operator) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form action="{{ route('operators.duplicate', $operator) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Duplicar</button>
                        </form>
                        <form action="{{ route('operators.destroy', $operator) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar operador?">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center py-4 text-body-secondary">Aún no hay operadores registrados.</td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>
@endsection