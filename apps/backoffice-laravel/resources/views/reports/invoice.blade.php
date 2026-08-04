@extends('layouts.report')

@section('title', 'Recibo de Pago #' . $booking->id)

@section('content')
<div class="report-header">
    <div class="brand-box">
        @if($settings['branding']['brand_logo_print'])
            <img src="{{ Storage::disk('public')->url($settings['branding']['brand_logo_print']) }}" class="logo">
        @endif
        <h1 class="business-name">{{ $settings['branding']['brand_business_name'] }}</h1>
        <div class="fiscal-data">
            {{ $settings['fiscal']['fiscal_legal_name'] }}<br>
            {{ $settings['fiscal']['fiscal_id'] }}<br>
            {{ $settings['fiscal']['fiscal_address'] }}
        </div>
    </div>
    <div class="document-info">
        <h2 class="document-type" style="color: #27ae60;">Recibo de Pago</h2>
        <div class="document-number">{{ $booking->order_folio ?? ('Folio #R-'.str_pad($booking->id, 6, '0', STR_PAD_LEFT)) }}</div>
        <div class="document-date">{{ now()->format($settings['system']['date_format']) }}</div>
    </div>
</div>

<div class="info-grid">
    <div class="info-box">
        <div class="info-title">Cliente</div>
        <div class="info-content">
            <strong>{{ $booking->pet->client->full_name }}</strong><br>
            {{ $booking->pet->client->phones->first()?->number ?? '' }}
        </div>
    </div>
    <div class="info-box">
        <div class="info-title">Mascota</div>
        <div class="info-content">
            <strong>{{ $booking->pet->name }}</strong><br>
            {{ $booking->pet->breed ?? 'N/D' }} | Talla: {{ $booking->pet->size ? ucfirst($booking->pet->size) : 'N/D' }}
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Concepto / Servicio Realizado</th>
            <th class="text-right">Monto</th>
        </tr>
    </thead>
    <tbody>
        @if($acceptedQuote)
            @foreach($acceptedQuote->items as $item)
                <tr>
                    <td>
                        {{ $item->name() }}
                        @if((float) $item->quantity !== 1.0)
                            <span style="color: var(--secondary-color);">× {{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</span>
                        @endif
                    </td>
                    <td class="text-right">${{ number_format($item->lineTotal(), 2) }}</td>
                </tr>
            @endforeach
        @else
            {{-- Citas cobradas directo desde la app móvil (sin presupuesto/Quote de por medio) --}}
            @foreach($booking->services as $bookingService)
                <tr>
                    <td>{{ $bookingService->service?->name ?? '—' }}</td>
                    <td class="text-right">${{ number_format($bookingService->current_price ?? 0, 2) }}</td>
                </tr>
            @endforeach
        @endif
    </tbody>
</table>

<div class="totals-wrapper">
    <div class="totals-box">
        @php
            $documentTotal = (float) ($acceptedQuote?->total_amount ?? $booking->total_estimated_price ?? 0);
        @endphp
        <div class="total-row">
            <span>Subtotal</span>
            <span>${{ number_format($documentTotal, 2) }}</span>
        </div>

        @php
            $allPayments = collect();
            if ($acceptedQuote) {
                foreach ($acceptedQuote->cashLedgers as $e) {
                    $allPayments->push(['date' => $e->created_at, 'method' => $e->payment_method, 'dest' => 'Caja', 'amount' => $e->amount]);
                }
                foreach ($acceptedQuote->bankLedgers as $e) {
                    $allPayments->push(['date' => $e->created_at, 'method' => $e->payment_method, 'dest' => 'Banco', 'amount' => $e->amount]);
                }
            }
            foreach ($directPayments as $p) {
                $allPayments->push(['date' => $p->created_at, 'method' => $p->payment_method, 'dest' => $p->destination === 'banco' ? 'Banco' : 'Caja', 'amount' => $p->amount]);
            }
            $allPayments = $allPayments->sortBy('date');
        @endphp

        <div style="margin-top: 10px; border-top: 1px dashed var(--secondary-color); padding-top: 5px;">
            <div style="font-size: 9px; font-weight: bold; color: var(--secondary-color);">HISTORIAL DE PAGOS</div>
            @if($allPayments->isNotEmpty())
                @foreach($allPayments as $entry)
                    <div class="total-row" style="font-size: 11px; border: none; color: #7f8c8d;">
                        <span>{{ $entry['date']->format('d/m') }} · {{ $entry['method'] }} ({{ $entry['dest'] }})</span>
                        <span>- ${{ number_format($entry['amount'], 2) }}</span>
                    </div>
                @endforeach
            @else
                <div class="total-row" style="font-size: 11px; border: none; color: #7f8c8d;">
                    <span>Sin pagos registrados</span>
                    <span>$0.00</span>
                </div>
            @endif
        </div>

        @php
            $totalPaid = (float) $allPayments->sum('amount');
            $balance   = $documentTotal - $totalPaid;
        @endphp

        <div class="total-row grand-total">
            <span>{{ $balance > 0 ? 'SALDO PENDIENTE' : 'TOTAL LIQUIDADO' }}</span>
            <span>${{ number_format($balance > 0 ? $balance : $totalPaid, 2) }}</span>
        </div>
    </div>
</div>

@if($booking->notes || $booking->processNotes->isNotEmpty())
    <div style="margin-top: 25px; border-top: 1px dashed var(--secondary-color); padding-top: 10px;">
        <div style="font-size: 9px; font-weight: bold; color: var(--secondary-color);">NOTAS</div>
        @if($booking->notes)
            <div style="font-size: 11px; margin-top: 4px;">
                <strong>De la cita:</strong> {{ $booking->notes }}
            </div>
        @endif
        @foreach($booking->processNotes as $note)
            <div style="font-size: 11px; margin-top: 4px;">
                <strong>{{ $note->created_at->format('d/m H:i') }}{{ $note->user ? ' · '.$note->user->name : '' }}:</strong>
                {{ $note->note }}
            </div>
        @endforeach
    </div>
@endif

<div style="margin-top: 50px;">
    <div style="text-align: center;">
        <div style="font-size: 14px; font-weight: bold; color: #27ae60;">¡GRACIAS POR SU PREFERENCIA!</div>
        <p style="font-size: 10px; color: var(--secondary-color);">Este documento no es una factura electrónica (CFDI). Si requiere factura, favor de solicitarla en recepción.</p>
    </div>
</div>

<div class="signature-box" style="margin-top: 60px;">
    <div class="signature-line" style="width: 250px;">
        Recibí conforme (Firma del Cliente)
    </div>
</div>
@endsection
