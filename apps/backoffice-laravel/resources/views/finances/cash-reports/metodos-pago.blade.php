@php($screenDebugId = 'FinRepMet')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Reportes · Caja"
    title="Métodos de pago"
    subtitle="Cobros del período agrupados por método de pago real."
>
    <x-slot:actions>
        <a href="{{ route('finances.cash-reports.metodos-pago.pdf', request()->query()) }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#emailMetodosPago">
            <i class="bi bi-envelope"></i> Enviar por correo
        </button>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    @include('finances.cash-reports._filters')

    <p class="text-body-secondary small">{{ $dateFrom }} a {{ $dateTo }} — {{ $branchName }}</p>

    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="text-body-secondary small">Total cobrado</div>
        <div class="fs-4 fw-bold">${{ number_format($totalCobrado, 2) }}</div>
    </div></div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Método</th><th>Cobros</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                    @forelse($byMethod as $group)
                        <tr><td>{{ $group['method'] }}</td><td>{{ $group['count'] }}</td><td class="text-end">${{ number_format($group['amount'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-4">Sin cobros para este período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('finances.cash-reports._email-modal', ['emailModalId' => 'emailMetodosPago', 'emailRoute' => route('finances.cash-reports.metodos-pago.email')])
@endsection
