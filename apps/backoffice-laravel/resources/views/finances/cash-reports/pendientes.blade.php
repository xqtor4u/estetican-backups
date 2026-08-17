@php($screenDebugId = 'FinRepPen')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Reportes · Caja"
    title="Pendientes por cobrar"
    subtitle="Citas terminadas o en proceso con saldo pendiente real, sin cobrar."
>
    <x-slot:actions>
        <a href="{{ route('finances.cash-reports.pendientes.pdf') }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#emailPendientes">
            <i class="bi bi-envelope"></i> Enviar por correo
        </button>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    <p class="text-body-secondary small">Generado {{ $generatedAt }}</p>

    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="text-body-secondary small">Total pendiente ({{ $count }} {{ $count === 1 ? 'cita' : 'citas' }})</div>
        <div class="fs-4 fw-bold text-danger">${{ number_format($totalPendiente, 2) }}</div>
    </div></div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Mascota</th><th>Cliente</th><th>Cita</th><th>Estado</th><th class="text-end">Pendiente</th></tr></thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item['petName'] }}</td>
                            <td>{{ $item['clientName'] }}</td>
                            <td>{{ $item['scheduledAt'] }}</td>
                            <td><span class="badge rounded-pill text-bg-secondary">{{ $item['status'] }}</span></td>
                            <td class="text-end fw-semibold">${{ number_format($item['unpaid'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">No hay saldos pendientes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('finances.cash-reports._email-modal', ['emailModalId' => 'emailPendientes', 'emailRoute' => route('finances.cash-reports.pendientes.email')])
@endsection
