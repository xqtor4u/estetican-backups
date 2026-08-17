@php($screenDebugId = 'FinRepOpe')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Reportes · Caja"
    title="Por operador"
    subtitle="Entradas y salidas del período agrupadas por quién las registró."
>
    <x-slot:actions>
        <a href="{{ route('finances.cash-reports.por-operador.pdf', request()->query()) }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#emailPorOperador">
            <i class="bi bi-envelope"></i> Enviar por correo
        </button>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    @include('finances.cash-reports._filters')

    <p class="text-body-secondary small">{{ $dateFrom }} a {{ $dateTo }} — {{ $branchName }}</p>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Operador</th><th>Mov.</th><th class="text-end">Entradas</th><th class="text-end">Salidas</th></tr></thead>
                <tbody>
                    @forelse($byOperator as $op)
                        <tr>
                            <td>{{ $op['name'] }}</td>
                            <td>{{ $op['count'] }}</td>
                            <td class="text-end text-success">${{ number_format($op['totalEntradas'], 2) }}</td>
                            <td class="text-end text-danger">${{ number_format($op['totalSalidas'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-4">Sin movimientos para este período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('finances.cash-reports._email-modal', ['emailModalId' => 'emailPorOperador', 'emailRoute' => route('finances.cash-reports.por-operador.email')])
@endsection
