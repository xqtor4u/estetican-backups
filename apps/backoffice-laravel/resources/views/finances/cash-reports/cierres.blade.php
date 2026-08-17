@php($screenDebugId = 'FinRepCie')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Reportes · Caja"
    title="Cierre de turno"
    subtitle="Historial de cortes de caja ya cerrados, con su diferencia real."
>
    <x-slot:actions>
        <a href="{{ route('finances.cash-reports.cierres.pdf', request()->query()) }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#emailCierres">
            <i class="bi bi-envelope"></i> Enviar por correo
        </button>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    @include('finances.cash-reports._filters')

    <p class="text-body-secondary small">{{ $dateFrom }} a {{ $dateTo }} — {{ $branchName }}</p>

    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="text-body-secondary small">Diferencia total ({{ $count }} {{ $count === 1 ? 'cierre' : 'cierres' }})</div>
        <div class="fs-4 fw-bold {{ $totalDifference >= 0 ? 'text-success' : 'text-danger' }}">
            {{ $totalDifference >= 0 ? '+' : '-' }}${{ number_format(abs($totalDifference), 2) }}
        </div>
    </div></div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Sucursal</th><th>Cerrado por</th><th>Fecha</th>
                        <th class="text-end">Fondo inicial</th><th class="text-end">Esperado</th>
                        <th class="text-end">Real contado</th><th class="text-end">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item['branchName'] }}</td>
                            <td>{{ $item['closedBy'] }}</td>
                            <td>{{ $item['closedAt'] }}</td>
                            <td class="text-end">${{ number_format($item['openingAmount'], 2) }}</td>
                            <td class="text-end">${{ number_format($item['expectedAmount'], 2) }}</td>
                            <td class="text-end">${{ number_format($item['closingAmount'], 2) }}</td>
                            <td class="text-end {{ $item['difference'] >= 0 ? 'text-success' : 'text-danger' }} fw-semibold">
                                {{ $item['difference'] >= 0 ? '+' : '-' }}${{ number_format(abs($item['difference']), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">No hay cierres para este período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('finances.cash-reports._email-modal', ['emailModalId' => 'emailCierres', 'emailRoute' => route('finances.cash-reports.cierres.email')])
@endsection
