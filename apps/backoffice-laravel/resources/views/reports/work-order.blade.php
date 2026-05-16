@extends('layouts.report')

@section('title', 'Orden de Trabajo #' . $booking->id)

@section('content')
<div class="report-header">
    <div class="brand-box">
        @if($settings['branding']['brand_logo_print'])
            <img src="{{ Storage::disk('public')->url($settings['branding']['brand_logo_print']) }}" class="logo">
        @endif
        <h1 class="business-name">{{ $settings['branding']['brand_business_name'] }}</h1>
        <div class="fiscal-data">
            ORDEN DE CONTROL INTERNO
        </div>
    </div>
    <div class="document-info">
        <h2 class="document-type" style="color: #f39c12;">Orden de Trabajo</h2>
        <div class="document-number">Sesión #{{ $booking->id }}</div>
        <div class="document-date">{{ now()->format($datetimeFormat) }}</div>
    </div>
</div>

<div class="info-grid">
    <div class="info-box">
        <div class="info-title">Paciente / Mascota</div>
        <div class="info-content">
            <div style="font-size: 16px; font-weight: bold;">{{ $booking->pet->name }}</div>
            <div>{{ ucfirst($booking->pet->species ?? '') }} | {{ $booking->pet->breed ?? 'N/D' }}</div>
            <div>Talla: <strong>{{ $booking->pet->size ? ucfirst($booking->pet->size) : 'N/D' }}</strong>{{ $booking->pet->age_description ? ' · ' . $booking->pet->age_description : '' }}</div>
            <div style="margin-top: 5px;">
                <span class="badge" style="background: #e74c3c; color: white;">ID: {{ $booking->pet->id }}</span>
            </div>
        </div>
    </div>
    <div class="info-box">
        <div class="info-title">Logística y Ubicación</div>
        <div class="info-content">
            <div class="info-row">
                <span class="info-label">Espacio:</span>
                <span style="font-weight: bold; font-size: 14px;">{{ $booking->resourceAllocations->firstWhere('allocation_type', 'reserved')?->resource?->name ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Ingreso:</span>
                <span>{{ $booking->scheduled_at?->format($datetimeFormat) }} hs</span>
            </div>
        </div>
    </div>
</div>

@if($booking->pet->medicalAlerts->isNotEmpty())
<div style="margin-bottom: 20px; padding: 10px; border: 2px solid #e74c3c; border-radius: 8px; background: #fdf2f2;">
    <div style="color: #e74c3c; font-weight: bold; font-size: 10px; text-transform: uppercase;">⚠️ ALERTAS MÉDICAS / CUIDADOS ESPECIALES</div>
    <ul style="margin: 5px 0 0 20px; padding: 0; color: #c0392b;">
        @foreach($booking->pet->medicalAlerts as $alert)
            <li>{{ $alert->alert_text }}</li>
        @endforeach
    </ul>
</div>
@endif

<table>
    <thead>
        <tr>
            <th>Servicio a Realizar</th>
            <th>Especialista Asignado</th>
            <th class="text-center">Estado</th>
        </tr>
    </thead>
    <tbody>
        @if($acceptedQuote)
            @foreach($acceptedQuote->items as $item)
                <tr>
                    <td>
                        <div style="font-weight: bold;">{{ $item->service->name }}</div>
                        <div style="font-size: 9px; color: var(--secondary-color);">{{ $item->service->description }}</div>
                    </td>
                    <td>{{ $item->operator?->full_name ?? 'Pendiente de asignar' }}</td>
                    <td class="text-center">
                        <span class="badge">Pendiente</span>
                    </td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="3" class="text-center">No hay servicios aceptados registrados.</td>
            </tr>
        @endif
    </tbody>
</table>

<div class="info-box" style="margin-bottom: 30px;">
    <div class="info-title">Notas de Ingreso / Observaciones Operativas</div>
    <div class="info-content" style="min-height: 60px;">
        {{ $booking->notes ?: 'Sin observaciones adicionales.' }}
    </div>
</div>

<div class="signature-box" style="margin-top: 80px;">
    <div class="signature-line">
        Firma de Recepción (Especialista)
    </div>
    <div class="signature-line">
        Conformidad de Ingreso (Cliente)
    </div>
</div>

<div style="margin-top: 30px; font-size: 9px; color: #999; text-align: justify;">
    * Al firmar esta orden, el cliente autoriza la realización de los servicios descritos y acepta los términos de responsabilidad civil y médica establecidos en el contrato de adhesión. Se hace constar el estado físico de la mascota al momento del ingreso.
</div>
@endsection
