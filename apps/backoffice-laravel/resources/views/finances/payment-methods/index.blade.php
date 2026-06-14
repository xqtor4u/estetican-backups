@php($screenDebugId = 'FinPmInd')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas"
    title="Métodos de pago"
    subtitle="Cada método se liga a una cuenta contable que se abona al registrar el cobro."
>
    <x-slot:actions>
        <a href="{{ route('finances.payment-methods.create') }}" class="btn btn-primary">Nuevo método</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:110px">Código</th>
                        <th>Nombre</th>
                        <th style="width:110px">Tipo</th>
                        <th>Cuenta contable</th>
                        <th style="width:130px">Requiere ref.</th>
                        <th style="width:100px">Estado</th>
                        <th class="text-end" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($methods as $method)
                        @php
                            $typeLabels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'crypto' => 'Cripto', 'gateway' => 'Pasarela'];
                        @endphp
                        <tr class="{{ $method->is_active ? '' : 'table-secondary opacity-60' }}">
                            <td><code class="fw-semibold">{{ $method->code }}</code></td>
                            <td>{{ $method->name }}</td>
                            <td>
                                <span class="badge rounded-pill text-bg-secondary">
                                    {{ $typeLabels[$method->type] ?? $method->type }}
                                </span>
                            </td>
                            <td>
                                @if($method->account)
                                    <span class="fw-semibold">{{ $method->account->code }}</span>
                                    <span class="text-body-secondary ms-1">{{ $method->account->name }}</span>
                                @else
                                    <span class="text-warning">Sin cuenta asignada</span>
                                @endif
                            </td>
                            <td class="text-center">
                                {{ $method->requires_reference ? '✓ Sí' : '—' }}
                            </td>
                            <td>
                                <span class="catalog-status-badge {{ $method->is_active ? 'catalog-status-badge--active' : 'catalog-status-badge--inactive' }}">
                                    {{ $method->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('finances.payment-methods.edit', $method) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                                    <form action="{{ route('finances.payment-methods.destroy', $method) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            data-confirm="¿Eliminar método {{ $method->name }}?">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-body-secondary">No hay métodos de pago registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
