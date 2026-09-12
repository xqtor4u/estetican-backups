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

        <h3 class="h6 mt-3">Existencia por sucursal</h3>
        <table class="table table-sm mb-4">
            <thead><tr><th>Sucursal</th><th>Existencia</th></tr></thead>
            <tbody>
                @foreach($branches as $branch)
                    <tr>
                        <td>{{ $branch->name }}</td>
                        <td class="{{ ($branchStocks[$branch->id] ?? 0) < 0 ? 'text-danger' : '' }}">{{ $branchStocks[$branch->id] ?? 0 }}</td>
                    </tr>
                @endforeach
                @if($unassignedStock !== 0)
                    <tr>
                        <td class="text-muted">Sin sucursal / otras</td>
                        <td class="{{ $unassignedStock < 0 ? 'text-danger' : '' }}">{{ $unassignedStock }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        {{-- Las dos formas de abajo comparten nombres de campo (`quantity`/`notes`) — `old()` no
             sabe cuál de las dos se envió, así que se scopea a mano por un campo exclusivo de
             cada una (`type` solo existe en "Registrar", `from_branch_id` solo en "Transferir")
             para no repoblar la forma equivocada tras un error en la otra. --}}
        @php
            $movementSubmitted = old('type') !== null;
            $transferSubmitted = old('from_branch_id') !== null;
        @endphp
        <form action="{{ route('items.movements.store', $item) }}" method="POST" class="row g-2 align-items-end mb-4">
            @csrf
            <div class="col-md-2">
                <label for="movement-type" class="form-label small">Tipo</label>
                <select id="movement-type" name="type" class="form-select form-select-sm @error('type') is-invalid @enderror" required>
                    <option value="entrada" @selected($movementSubmitted && old('type') === 'entrada')>Entrada</option>
                    <option value="ajuste" @selected($movementSubmitted && old('type') === 'ajuste')>Ajuste</option>
                    <option value="perdida" @selected($movementSubmitted && old('type') === 'perdida')>Pérdida</option>
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="movement-direction" class="form-label small">Dirección (solo ajuste)</label>
                <select id="movement-direction" name="direction" class="form-select form-select-sm">
                    <option value="suma" @selected(! $movementSubmitted || old('direction', 'suma') === 'suma')>Suma</option>
                    <option value="resta" @selected($movementSubmitted && old('direction') === 'resta')>Resta</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="movement-quantity" class="form-label small">Cantidad</label>
                <input id="movement-quantity" type="number" name="quantity" class="form-control form-control-sm {{ $movementSubmitted && $errors->has('quantity') ? 'is-invalid' : '' }}" min="1" step="1" value="{{ $movementSubmitted ? old('quantity') : '' }}" required>
                @if($movementSubmitted) @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
            <div class="col-md-3">
                <label for="movement-branch" class="form-label small">Sucursal</label>
                <select id="movement-branch" name="branch_id" class="form-select form-select-sm @error('branch_id') is-invalid @enderror" required>
                    <option value="" disabled @selected(! $movementSubmitted || ! old('branch_id'))>Elegir sucursal</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($movementSubmitted && (string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="movement-notes" class="form-label small">Nota</label>
                <input id="movement-notes" type="text" name="notes" class="form-control form-control-sm" value="{{ $movementSubmitted ? old('notes') : '' }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Registrar</button>
            </div>
        </form>

        <h3 class="h6 mt-3">Transferir entre sucursales</h3>
        <form action="{{ route('items.movements.transfer', $item) }}" method="POST" class="row g-2 align-items-end mb-4">
            @csrf
            <div class="col-md-3">
                <label for="transfer-from-branch" class="form-label small">Sucursal origen</label>
                <select id="transfer-from-branch" name="from_branch_id" class="form-select form-select-sm @error('from_branch_id') is-invalid @enderror" required>
                    <option value="" disabled @selected(! $transferSubmitted || ! old('from_branch_id'))>Elegir sucursal</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($transferSubmitted && (string) old('from_branch_id') === (string) $branch->id)>{{ $branch->name }} ({{ $branchStocks[$branch->id] ?? 0 }})</option>
                    @endforeach
                </select>
                @error('from_branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="transfer-to-branch" class="form-label small">Sucursal destino</label>
                <select id="transfer-to-branch" name="to_branch_id" class="form-select form-select-sm @error('to_branch_id') is-invalid @enderror" required>
                    <option value="" disabled @selected(! $transferSubmitted || ! old('to_branch_id'))>Elegir sucursal</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($transferSubmitted && (string) old('to_branch_id') === (string) $branch->id)>{{ $branch->name }} ({{ $branchStocks[$branch->id] ?? 0 }})</option>
                    @endforeach
                </select>
                @error('to_branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="transfer-quantity" class="form-label small">Cantidad</label>
                <input id="transfer-quantity" type="number" name="quantity" class="form-control form-control-sm {{ $transferSubmitted && $errors->has('quantity') ? 'is-invalid' : '' }}" min="1" step="1" value="{{ $transferSubmitted ? old('quantity') : '' }}" required>
                @if($transferSubmitted) @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
            <div class="col-md-3">
                <label for="transfer-notes" class="form-label small">Nota</label>
                <input id="transfer-notes" type="text" name="notes" class="form-control form-control-sm" value="{{ $transferSubmitted ? old('notes') : '' }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Transferir</button>
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
                            <td>{{ match($movement->type) {
                                'entrada' => 'Entrada',
                                'ajuste' => 'Ajuste',
                                'perdida' => 'Pérdida',
                                'consumo_servicio' => 'Consumo por servicio',
                                'transferencia_salida' => 'Transferencia (salida)',
                                'transferencia_entrada' => 'Transferencia (entrada)',
                                default => ucfirst($movement->type),
                            } }}</td>
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
