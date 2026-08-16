@extends('layouts.pdf')

@section('title', 'Métodos de pago — ' . $dateFrom . ' a ' . $dateTo)
@section('document-type', 'Métodos de Pago')

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

<h3 class="section-title">Total cobrado</h3>
<p style="font-size: 16px; font-weight: bold;">${{ number_format($totalCobrado, 2) }}</p>

<h3 class="section-title">Desglose por método de pago</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Método</th>
            <th>Cobros</th>
            <th>Monto</th>
            <th>% del total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($byMethod as $group)
            <tr>
                <td>{{ $group['method'] }}</td>
                <td>{{ $group['count'] }}</td>
                <td>${{ number_format($group['amount'], 2) }}</td>
                <td>{{ $totalCobrado > 0 ? number_format($group['amount'] / $totalCobrado * 100, 1) : '0.0' }}%</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Sin cobros para este período.</td></tr>
        @endforelse
    </tbody>
</table>

<p class="muted" style="margin-top: 20px;">
    Solo incluye cobros a clientes (efectivo, tarjeta, transferencia, etc.) — no incluye
    movimientos manuales de caja (retiros, depósitos, gastos), que no tienen un método de pago
    en este sentido. Ver "Resumen de caja" para esos.
</p>

<p class="muted">Generado por {{ $generatedBy }}.</p>
@endsection
