@php($screenDebugId = 'FinRepRes')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Reportes · Caja"
    title="Resumen de caja"
    subtitle="Total cobrado, entradas y salidas del período, desglosado por tipo."
>
    <x-slot:actions>
        <a href="{{ route('finances.cash-reports.resumen.pdf', request()->query()) }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#emailResumen">
            <i class="bi bi-envelope"></i> Enviar por correo
        </button>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    @include('finances.cash-reports._filters')

    <p class="text-body-secondary small">{{ $dateFrom }} a {{ $dateTo }} — {{ $branchName }}</p>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-body-secondary small">Total cobrado</div>
                <div class="fs-4 fw-bold">${{ number_format($totalCobrado, 2) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-body-secondary small">Entradas</div>
                <div class="fs-4 fw-bold text-success">${{ number_format($totalEntradas, 2) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-body-secondary small">Salidas</div>
                <div class="fs-4 fw-bold text-danger">${{ number_format($totalSalidas, 2) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-body-secondary small">Neto del período</div>
                <div class="fs-4 fw-bold {{ $neto >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format($neto, 2) }}</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">Desglose — Entradas</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Tipo</th><th>Mov.</th><th class="text-end">Monto</th></tr></thead>
                        <tbody>
                            @forelse($byTypeEntradas as $group)
                                <tr><td>{{ $group['label'] }}</td><td>{{ $group['count'] }}</td><td class="text-end">${{ number_format($group['amount'], 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">Sin entradas para este período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">Desglose — Salidas</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Tipo</th><th>Mov.</th><th class="text-end">Monto</th></tr></thead>
                        <tbody>
                            @forelse($byTypeSalidas as $group)
                                <tr><td>{{ $group['label'] }}</td><td>{{ $group['count'] }}</td><td class="text-end">${{ number_format($group['amount'], 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">Sin salidas para este período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('finances.cash-reports._email-modal', ['emailModalId' => 'emailResumen', 'emailRoute' => route('finances.cash-reports.resumen.email')])
@endsection
