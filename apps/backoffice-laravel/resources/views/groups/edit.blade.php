@php
    $screenDebugId = 'GrpEdi';

    $page = \App\Support\Pages\GroupsPage::edit($group);
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
/>

<div class="card">
    <div class="card-body">
        <form action="{{ route('groups.update', $group) }}" method="POST">
            @csrf
            @method('PUT')
            @include('groups.partials.form', ['submitLabel' => 'Actualizar grupo'])
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h2 class="h5">Componentes del grupo</h2>
        <p class="text-muted small">Precio total: <strong>${{ number_format($group->calculatedPrice(), 2) }}</strong> — suma de cantidad × precio vigente de cada componente. Se recalcula solo si cambia el precio del catálogo, sin editar nada aquí.</p>

        <form action="{{ route('groups.components.store', $group) }}" method="POST" class="row g-2 align-items-end mb-4">
            @csrf
            <div class="col-md-4">
                <label for="component-service" class="form-label small">Servicio</label>
                <select id="component-service" name="service_id" class="form-select form-select-sm">
                    <option value="">— Ninguno —</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }} (${{ number_format($service->price, 2) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="component-item" class="form-label small">Artículo</label>
                <select id="component-item" name="item_id" class="form-select form-select-sm">
                    <option value="">— Ninguno —</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name }} (${{ number_format($item->price ?? 0, 2) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="component-quantity" class="form-label small">Cantidad</label>
                <input id="component-quantity" type="number" name="quantity" class="form-control form-control-sm" min="0.01" step="0.01" value="1" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Agregar</button>
            </div>
            <div class="col-12">
                <div class="form-text">Elige un Servicio <strong>o</strong> un Artículo, no ambos. Cantidad admite decimales (ej. 0.5 hrs).</div>
            </div>
        </form>

        @if($group->components->isEmpty())
            <p class="text-muted mb-0">Sin componentes todavía — el grupo no se puede agregar a una cotización hasta que tenga al menos uno.</p>
        @else
            <table class="table table-sm mb-0">
                <thead><tr><th></th><th>Componente</th><th>Tipo</th><th>Cantidad</th><th>Precio unitario</th><th>Subtotal</th><th></th></tr></thead>
                <tbody>
                    @foreach($group->components as $component)
                        <tr>
                            <td style="width: 40px;">
                                @if($component->item && $component->item->photo_path)
                                    <img src="{{ $component->item->photo_thumbnail_url }}" alt="" class="rounded-2" style="width: 32px; height: 32px; object-fit: cover;">
                                @endif
                            </td>
                            <td>{{ $component->name() }}</td>
                            <td>{{ $component->service_id ? 'Servicio' : 'Artículo' }}</td>
                            <td>{{ rtrim(rtrim(number_format($component->quantity, 2), '0'), '.') }}</td>
                            <td>${{ number_format($component->unitPrice(), 2) }}</td>
                            <td>${{ number_format($component->quantity * $component->unitPrice(), 2) }}</td>
                            <td class="text-end">
                                <form action="{{ route('groups.components.destroy', [$group, $component]) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Quitar componente?">Quitar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
