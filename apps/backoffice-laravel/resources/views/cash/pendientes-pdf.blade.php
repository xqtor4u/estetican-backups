@extends('layouts.pdf')

@section('title', 'Pendientes por cobrar — ' . $generatedAt)
@section('document-type', 'Pendientes por Cobrar')

@section('content')
<h3 class="section-title">Total pendiente</h3>
<p style="font-size: 16px; font-weight: bold;">${{ number_format($totalPendiente, 2) }} ({{ $count }} {{ $count === 1 ? 'servicio' : 'servicios' }})</p>

<h3 class="section-title">Servicios sin pagar</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Mascota</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Pendiente</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $item)
            <tr>
                <td>{{ $item['scheduledAt'] }}</td>
                <td>{{ $item['petName'] }}</td>
                <td>{{ $item['clientName'] }}</td>
                <td><span class="badge">{{ $item['status'] }}</span></td>
                <td>${{ number_format($item['unpaid'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No hay servicios pendientes de cobro.</td></tr>
        @endforelse
    </tbody>
</table>

<p class="muted" style="margin-top: 20px;">
    Lista completa, sin filtro de fecha ni sucursal — es un corte de "ahora mismo", no un
    reporte por período. Solo incluye citas terminadas o en proceso, nunca canceladas.
</p>

<p class="muted">Generado por {{ $generatedBy }}.</p>
@endsection
