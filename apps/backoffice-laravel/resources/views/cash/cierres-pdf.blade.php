@extends('layouts.pdf')

@section('title', 'Cierres de turno — ' . $dateFrom . ' a ' . $dateTo)
@section('document-type', 'Cierres de Turno')

@section('content')
@php
    // El signo va antes del "$", nunca "$-5.00" — number_format() ya incluye el "-" para
    // negativos, así que hay que separar signo y valor absoluto en vez de concatenar directo.
    $fmtSigned = fn ($n) => ($n >= 0 ? '+' : '-') . '$' . number_format(abs($n), 2);
    $showBranchColumn = $branchName === 'Todas las sucursales';
@endphp
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

<h3 class="section-title">{{ $count }} {{ $count === 1 ? 'cierre' : 'cierres' }} — diferencia acumulada: {{ $fmtSigned($totalDifference) }}</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Cerrado</th>
            @if($showBranchColumn)<th>Sucursal</th>@endif
            <th>Cerrado por</th>
            <th>Fondo inicial</th>
            <th>Esperado</th>
            <th>Contado</th>
            <th>Diferencia</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $item)
            <tr>
                <td>{{ $item['closedAt'] }}</td>
                @if($showBranchColumn)<td>{{ $item['branchName'] }}</td>@endif
                <td>{{ $item['closedBy'] }}</td>
                <td>${{ number_format($item['openingAmount'], 2) }}</td>
                <td>${{ number_format($item['expectedAmount'], 2) }}</td>
                <td>${{ number_format($item['closingAmount'], 2) }}</td>
                <td>{{ $fmtSigned($item['difference']) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $showBranchColumn ? 7 : 6 }}" class="muted">Sin cierres de turno para este período.</td></tr>
        @endforelse
    </tbody>
</table>

<p class="muted" style="margin-top: 20px;">
    "Esperado"/"Diferencia" son los valores reales guardados al momento del cierre — no se
    recalculan acá.
</p>

<p class="muted">Generado por {{ $generatedBy }}.</p>
@endsection
