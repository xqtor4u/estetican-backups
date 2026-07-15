@extends('layouts.report')

@section('title', 'Ficha clínica — ' . $pet->name)

@section('content')
<div class="report-header">
    <div class="brand-box">
        @if($settings['branding']['brand_logo_print'])
            <img src="{{ Storage::disk('public')->url($settings['branding']['brand_logo_print']) }}" class="logo">
        @endif
        <h1 class="business-name">{{ $settings['branding']['brand_business_name'] }}</h1>
    </div>
    <div class="document-info">
        <h2 class="document-type">Ficha / Carpeta Clínica</h2>
        <div class="document-date">{{ now()->format('d/m/Y') }}</div>
    </div>
</div>

<p style="font-size: 12px; color: #666;">
    Documento de referencia para atención veterinaria — no sustituye el expediente clínico completo del sistema.
</p>

<div class="info-grid">
    <div class="info-box">
        <div class="info-title">Mascota</div>
        <div class="info-content">
            <strong>{{ $pet->name }}</strong><br>
            {{ $pet->species ?? 'N/D' }} — {{ $pet->breed ?? 'N/D' }}<br>
            Sexo: {{ $pet->sex ?? 'N/D' }} | Esterilizada: {{ $pet->is_sterilized ? 'Sí' : 'No' }}<br>
            @if($pet->birth_date) Nacimiento: {{ $pet->birth_date->format('d/m/Y') }} @endif
        </div>
    </div>
    <div class="info-box">
        <div class="info-title">Dueño</div>
        <div class="info-content">
            <strong>{{ $pet->client?->full_name }}</strong><br>
            {{ $pet->client?->email ?? '' }}<br>
            {{ $pet->client?->phones->first()?->number ?? '' }}
        </div>
    </div>
</div>

<h3 class="h6">Alergias activas</h3>
@forelse($pet->allergies as $allergy)
    <p>{{ $allergy->allergen }} ({{ $allergy->allergen_type }}) — severidad: {{ $allergy->severity }}@if($allergy->reaction_description) — {{ $allergy->reaction_description }} @endif</p>
@empty
    <p>Ninguna registrada.</p>
@endforelse

<h3 class="h6">Condiciones crónicas</h3>
@forelse($pet->conditions as $condition)
    <p>{{ $condition->name }} — {{ $condition->status }}</p>
@empty
    <p>Ninguna registrada.</p>
@endforelse

<h3 class="h6">Vacunas</h3>
@forelse($pet->vaccinations as $vaccination)
    <p>{{ $vaccination->vaccine_name }} — aplicada {{ $vaccination->applied_at?->format('d/m/Y') ?? 'N/D' }}, vigente hasta {{ $vaccination->expires_at?->format('d/m/Y') ?? 'N/D' }}</p>
@empty
    <p>Ninguna registrada.</p>
@endforelse

<h3 class="h6">Últimas visitas clínicas</h3>
@forelse($pet->clinicalVisits as $visit)
    <p>{{ $visit->visited_at->format('d/m/Y') }} — {{ $visit->reason_for_visit }} ({{ $visit->status }})</p>
@empty
    <p>Sin visitas registradas en el sistema.</p>
@endforelse
@endsection
