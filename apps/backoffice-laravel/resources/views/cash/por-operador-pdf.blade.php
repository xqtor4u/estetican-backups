@extends('layouts.pdf')

@section('title', 'Por operador — ' . $dateFrom . ' a ' . $dateTo)
@section('document-type', 'Por Operador')

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

<h3 class="section-title">Movimientos por operador</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Operador</th>
            <th>Movimientos</th>
            <th>Entradas</th>
            <th>Salidas</th>
        </tr>
    </thead>
    <tbody>
        @forelse($byOperator as $op)
            <tr>
                <td>{{ $op['name'] }}</td>
                <td>{{ $op['count'] }}</td>
                <td>${{ number_format($op['totalEntradas'], 2) }}</td>
                <td>${{ number_format($op['totalSalidas'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Sin movimientos para este período.</td></tr>
        @endforelse
    </tbody>
</table>

<p class="muted" style="margin-top: 20px;">
    Entradas y salidas se muestran por separado — una reversión la registra quien la revierte,
    no necesariamente el mismo operador del movimiento original.
</p>

<p class="muted">Generado por {{ $generatedBy }}.</p>
@endsection
