@php
    $screenDebugId = 'ArtEdi';

    $page = \App\Support\Pages\ItemsPage::edit($item);
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
        <form action="{{ route('items.update', $item) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('items.partials.form', ['submitLabel' => 'Actualizar artículo'])
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h2 class="h5">Movimientos de inventario</h2>
        <p class="text-muted small">Existencia actual: <strong>{{ $item->stock_quantity }}</strong>. Cada movimiento queda en el histórico, sin poder editarse ni borrarse (ver "ajuste" para corregir un conteo).</p>

        <form action="{{ route('items.movements.store', $item) }}" method="POST" class="row g-2 align-items-end mb-4">
            @csrf
            <div class="col-md-2">
                <label for="movement-type" class="form-label small">Tipo</label>
                <select id="movement-type" name="type" class="form-select form-select-sm" required>
                    <option value="entrada">Entrada</option>
                    <option value="ajuste">Ajuste</option>
                    <option value="perdida">Pérdida</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="movement-direction" class="form-label small">Dirección (solo ajuste)</label>
                <select id="movement-direction" name="direction" class="form-select form-select-sm">
                    <option value="suma">Suma</option>
                    <option value="resta">Resta</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="movement-quantity" class="form-label small">Cantidad</label>
                <input id="movement-quantity" type="number" name="quantity" class="form-control form-control-sm" min="1" step="1" required>
            </div>
            <div class="col-md-3">
                <label for="movement-branch" class="form-label small">Sucursal (opcional)</label>
                <select id="movement-branch" name="branch_id" class="form-select form-select-sm">
                    <option value="">Sin especificar</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="movement-notes" class="form-label small">Nota</label>
                <input id="movement-notes" type="text" name="notes" class="form-control form-control-sm">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Registrar</button>
            </div>
        </form>

        @if($movements->isEmpty())
            <p class="text-muted mb-0">Sin movimientos registrados todavía.</p>
        @else
            <table class="table table-sm mb-0">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>Sucursal</th><th>Nota</th></tr></thead>
                <tbody>
                    @foreach($movements as $movement)
                        <tr>
                            <td>{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ ucfirst($movement->type) }}</td>
                            <td class="{{ $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                            <td>{{ $movement->branch?->name ?? '—' }}</td>
                            <td>{{ $movement->notes ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
