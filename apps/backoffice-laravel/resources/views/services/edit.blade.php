@php
    $screenDebugId = 'SerEdi';

    $page = \App\Support\Pages\ServicesPage::edit($service);
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
        <a href="{{ route('services.show', $service) }}" class="btn btn-outline-secondary">Ver detalle</a>
    </x-slot:actions>
</x-page-header>

@php
    $originLabels = [
        'open_to_all' => ['Abierto a todos', 'bg-secondary'],
        'template' => ['Plantilla de rol', 'bg-info text-dark'],
        'grant' => ['Capacidad directa', 'bg-success'],
        'none' => ['—', 'bg-light text-dark'],
    ];
@endphp

<div class="card">
    <div class="card-body">
        <form action="{{ route('services.update', $service) }}" method="POST">
            @csrf
            @method('PUT')
            @include('services.partials.form', ['submitLabel' => 'Actualizar servicio'])
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h2 class="h6 mb-0">Quién lo realiza</h2>
        <p class="small text-muted mb-0">
            Qué operadores pueden agendar y ejecutar este servicio. Un servicio puede arrancar
            <em>abierto a todos</em> y luego acotarse por rol (plantilla) o persona (capacidad directa).
        </p>
    </div>
    <div class="card-body">
        <form action="{{ route('services.eligibility.update', $service) }}" method="POST" class="mb-4">
            @csrf
            @method('PUT')
            <div class="form-check form-switch">
                <input type="hidden" name="open_to_all_operators" value="0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="open_to_all_operators" name="open_to_all_operators" value="1"
                       @checked((bool) old('open_to_all_operators', $service->open_to_all_operators))
                       onchange="this.form.submit()">
                <label class="form-check-label fw-bold" for="open_to_all_operators">
                    Abierto a todos los operadores
                </label>
                <div class="form-text">
                    Activo: cualquier operador puede realizarlo (se ignoran plantillas y capacidades).
                    Apagado: sólo quien lo traiga por plantilla de rol o por capacidad directa.
                </div>
            </div>
        </form>

        @if($templateRoles->isNotEmpty())
            <p class="small mb-2">
                <span class="text-muted">Roles cuya plantilla incluye este servicio:</span>
                @foreach($templateRoles as $role)
                    <a href="{{ route('operator-roles.edit', $role) }}" class="badge bg-info text-dark text-decoration-none">{{ $role->name }}</a>
                @endforeach
            </p>
        @endif

        @if($service->open_to_all_operators)
            <x-empty-state
                title="Está abierto a todos."
                subtitle="Apaga el interruptor de arriba para gobernar quién lo realiza por rol o por persona." />
        @else
            <x-list-table>
                <thead>
                    <tr>
                        <th>Operador</th>
                        <th>Origen</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($eligibleOperators as $row)
                        @php([$label, $cls] = $originLabels[$row['origin']] ?? $originLabels['none'])
                        <tr>
                            <td>{{ $row['operator']->full_name }}</td>
                            <td><span class="badge {{ $cls }}">{{ $label }}</span></td>
                            <td class="text-end">
                                @if($row['origin'] === 'grant')
                                    <form action="{{ route('services.operator-capabilities.destroy', [$service, $row['operator']]) }}" method="POST" onsubmit="return confirm('¿Quitar a {{ $row['operator']->full_name }} de este servicio?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Quitar</button>
                                    </form>
                                @elseif($row['origin'] === 'template')
                                    <span class="small text-muted">Se quita desde la ficha del operador</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">Nadie puede realizar este servicio todavía.</td></tr>
                    @endforelse
                </tbody>
            </x-list-table>

            @if($addableOperators->isNotEmpty())
                <form action="{{ route('services.operator-capabilities.store', $service) }}" method="POST" class="d-flex gap-2 mt-3" style="max-width: 480px;">
                    @csrf
                    <select name="operator_id" class="form-select" required>
                        <option value="">Agregar operador…</option>
                        @foreach($addableOperators as $op)
                            <option value="{{ $op->id }}">{{ $op->full_name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary text-nowrap">Agregar</button>
                </form>
            @endif
        @endif
    </div>
</div>
@endsection