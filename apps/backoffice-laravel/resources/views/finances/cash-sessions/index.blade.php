@php($screenDebugId = 'FinCsInd')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas"
    title="Sesiones de caja"
    subtitle="Historial de aperturas y cortes de caja por fecha."
>
    <x-slot:actions>
        <a href="{{ route('finances.cash-registers.index') }}" class="btn btn-outline-secondary">Ver cajas</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Caja</th>
                        <th>Sucursal</th>
                        <th>Apertura</th>
                        <th>Fondo inicial</th>
                        <th>Cierre</th>
                        <th>Diferencia</th>
                        <th style="width:90px">Estado</th>
                        <th class="text-end" style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr>
                            <td class="text-body-secondary small">{{ $session->id }}</td>
                            <td class="fw-semibold">{{ $session->cashRegister?->name ?? '—' }}</td>
                            <td>{{ $session->branch?->name ?? '—' }}</td>
                            <td>
                                <div>{{ $session->opened_at->format('d/m/Y H:i') }}</div>
                                <div class="text-body-secondary small">{{ $session->openedBy?->name }}</div>
                            </td>
                            <td>${{ number_format($session->opening_amount, 2) }}</td>
                            <td>
                                @if($session->closed_at)
                                    <div>{{ $session->closed_at->format('d/m/Y H:i') }}</div>
                                    <div class="text-body-secondary small">{{ $session->closedBy?->name }}</div>
                                @else
                                    <span class="text-body-tertiary">—</span>
                                @endif
                            </td>
                            <td>
                                @if($session->difference !== null)
                                    <span class="{{ $session->difference >= 0 ? 'text-success' : 'text-danger' }} fw-semibold">
                                        {{ $session->difference >= 0 ? '+' : '' }}${{ number_format($session->difference, 2) }}
                                    </span>
                                @else
                                    <span class="text-body-tertiary">—</span>
                                @endif
                            </td>
                            <td>
                                @if($session->isOpen())
                                    <span class="badge rounded-pill text-bg-success">Abierta</span>
                                @else
                                    <span class="badge rounded-pill text-bg-secondary">Cerrada</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('finances.cash-sessions.show', $session) }}"
                                   class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-body-secondary">
                                No hay sesiones registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sessions->hasPages())
            <div class="card-footer bg-transparent">
                {{ $sessions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
