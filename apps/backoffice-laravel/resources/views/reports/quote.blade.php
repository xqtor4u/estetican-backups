@extends('layouts.report')

@section('title', 'Presupuesto #' . $quote->id)

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
        <h2 class="document-type">Presupuesto</h2>
        <div class="document-number"># {{ str_pad($quote->id, 6, '0', STR_PAD_LEFT) }}</div>
        <div class="document-date">{{ now()->format($settings['system']['date_format']) }}</div>
    </div>
</div>

<div class="info-grid">
    <div class="info-box">
        <div class="info-title">Cliente</div>
        <div class="info-content">
            <strong>{{ $booking->pet->client->full_name }}</strong><br>
            {{ $booking->pet->client->email }}<br>
            {{ $booking->pet->client->phones->first()?->number ?? '' }}
        </div>
    </div>
    <div class="info-box">
        <div class="info-title">Paciente / Mascota</div>
        <div class="info-content">
            <div class="info-row">
                <span class="info-label">Nombre:</span>
                <span>{{ $booking->pet->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Especie:</span>
                <span>{{ $booking->pet->species }} ({{ $booking->pet->breed }})</span>
            </div>
            <div class="info-row">
                <span class="info-label">Talla:</span>
                <span>{{ $booking->pet->size ? ucfirst($booking->pet->size) : 'N/D' }}</span>
            </div>
            @if($booking->pet->age_description)
            <div class="info-row">
                <span class="info-label">Edad:</span>
                <span>{{ $booking->pet->age_description }}</span>
            </div>
            @endif
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Servicio / Concepto</th>
            <th class="text-center">Cant.</th>
            <th class="text-right">Precio Unit.</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($quote->items as $item)
            <tr>
                <td>
                    <div style="font-weight: bold;">{{ $item->name() }}</div>
                    <div style="font-size: 9px; color: var(--secondary-color);">{{ $item->service?->description }}</div>
                </td>
                <td class="text-center">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                <td class="text-right">${{ number_format($item->unitPrice(), 2) }}</td>
                <td class="text-right">${{ number_format($item->lineTotal(), 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="totals-wrapper">
    <div class="totals-box">
        <div class="total-row">
            <span>Subtotal</span>
            <span>${{ number_format($quote->total_amount, 2) }}</span>
        </div>
        <div class="total-row">
            <span>IVA (0%)</span>
            <span>$0.00</span>
        </div>
        <div class="total-row grand-total">
            <span>TOTAL ({{ $settings['system']['currency_code'] }})</span>
            <span>${{ number_format($quote->total_amount, 2) }}</span>
        </div>
    </div>
</div>

<div style="margin-top: 40px; padding: 15px; background: var(--bg-light); border-radius: 8px;">
    <div style="font-size: 10px; font-weight: bold; margin-bottom: 5px; color: var(--secondary-color);">TÉRMINOS Y CONDICIONES</div>
    <div style="font-size: 9px; line-height: 1.3;">
        - Este presupuesto tiene una validez de 15 días naturales.<br>
        - Los precios pueden variar si se detectan complicaciones no previstas durante el ingreso.<br>
        - En servicios quirúrgicos o de estética avanzada, se requiere un anticipo del 50%.<br>
        - El pago total debe realizarse al momento de recoger a la mascota.
    </div>
</div>

<div class="signature-box">
    <div class="signature-line">
        Firma de Autorización (Cliente)
    </div>
    <div class="signature-line">
        Sello y Firma (EstetiCAN)
    </div>
</div>
@endsection
