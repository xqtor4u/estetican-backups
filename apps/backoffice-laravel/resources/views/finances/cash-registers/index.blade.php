@php($screenDebugId = 'FinCrInd')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas"
    title="Cajas"
    subtitle="Registro de cajas físicas por sucursal. Cada caja tiene su propia sesión de apertura y corte."
>
    <x-slot:actions>
        <a href="{{ route('finances.cash-registers.create') }}" class="btn btn-primary">Nueva caja</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th style="width:160px">Sucursal</th>
                        <th style="width:160px">Sesión actual</th>
                        <th style="width:100px">Estado</th>
                        <th class="text-end" style="width:130px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registers as $register)
                        <tr class="{{ $register->is_active ? '' : 'table-secondary opacity-60' }}">
                            <td class="fw-semibold">{{ $register->name }}</td>
                            <td>{{ $register->branch?->name ?? '—' }}</td>
                            <td>
                                @if($register->activeSession)
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge rounded-pill text-bg-success">Abierta</span>
                                        <div class="text-body-secondary small">por {{ $register->activeSession->openedBy?->name }}</div>
                                        <div class="d-flex gap-1 flex-wrap mt-1">
                                            <a href="{{ route('finances.cash-sessions.show', $register->activeSession) }}"
                                               class="btn btn-sm btn-outline-primary">Ver sesión</a>
                                            <a href="{{ route('finances.cash-sessions.close', $register->activeSession) }}"
                                               class="btn btn-sm btn-warning">Hacer corte</a>
                                        </div>
                                    </div>
                                @elseif($register->is_active)
                                    <a href="{{ route('finances.cash-sessions.open', $register) }}"
                                       class="btn btn-sm btn-outline-success">
                                        Abrir sesión
                                    </a>
                                @else
                                    <span class="text-body-tertiary">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="catalog-status-badge {{ $register->is_active ? 'catalog-status-badge--active' : 'catalog-status-badge--inactive' }}">
                                    {{ $register->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('finances.cash-registers.edit', $register) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                                    <form action="{{ route('finances.cash-registers.destroy', $register) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            data-confirm="¿Eliminar la caja {{ $register->name }}?">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-body-secondary">No hay cajas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
