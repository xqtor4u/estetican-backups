@extends('layouts.pdf')

@section('title', 'Resumen de caja — ' . $dateFrom . ' a ' . $dateTo)
@section('document-type', 'Resumen de Caja')

@section('content')
<table class="info-table">
    <tr>
        <td>
            <div class="info-title">Período</div>
            <strong>{{ $dateFrom }}</strong> a <strong>{{ $dateTo }}</strong>
        </td>
        <td>
            <div class="info-title">Sucursal</div>
            <strong>{{ $branchName }}</strong>
        </td>
    </tr>
</table>

<h3 class="section-title">Totales</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Total cobrado</th>
            <th>Entradas</th>
            <th>Salidas</th>
            <th>Neto del período</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>${{ number_format($totalCobrado, 2) }}</td>
            <td>${{ number_format($totalEntradas, 2) }}</td>
            <td>${{ number_format($totalSalidas, 2) }}</td>
            <td>${{ number_format($neto, 2) }}</td>
        </tr>
    </tbody>
</table>

<h3 class="section-title">Desglose — Entradas</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Tipo</th>
            <th>Movimientos</th>
            <th>Monto</th>
        </tr>
    </thead>
    <tbody>
        @forelse($byTypeEntradas as $group)
            <tr>
                <td>{{ $group['label'] }}</td>
                <td>{{ $group['count'] }}</td>
                <td>${{ number_format($group['amount'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Sin entradas para este período.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 class="section-title">Desglose — Salidas</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Tipo</th>
            <th>Movimientos</th>
            <th>Monto</th>
        </tr>
    </thead>
    <tbody>
        @forelse($byTypeSalidas as $group)
            <tr>
                <td>{{ $group['label'] }}</td>
                <td>{{ $group['count'] }}</td>
                <td>${{ number_format($group['amount'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Sin salidas para este período.</td></tr>
        @endforelse
    </tbody>
</table>

@if($byTypeEntradas->pluck('type')->intersect($byTypeSalidas->pluck('type'))->isNotEmpty())
    <p class="muted">Nota: un mismo tipo puede aparecer en ambas tablas si alguno de sus movimientos fue revertido — el original y su reversión se cancelan entre sí en el neto.</p>
@endif

<p class="muted" style="margin-top: 20px;">Generado por {{ $generatedBy }}.</p>
@endsection
