@extends('layouts.pdf')

@section('title', 'Expediente clínico — ' . $pet->name)
@section('document-type', 'Expediente Clínico')

@php
    $visitTypeLabels = [
        'consultation' => 'Consulta',
        'follow_up' => 'Seguimiento',
        'emergency' => 'Urgencia',
        'pre_grooming_check' => 'Chequeo previo a grooming',
        'vaccination' => 'Vacunación',
    ];
    $attachmentTypeLabels = [
        'lab_result' => 'Resultado de laboratorio',
        'xray' => 'Radiografía',
        'ultrasound' => 'Ultrasonido',
        'other_imaging' => 'Otra imagenología',
        'referral_letter' => 'Carta de referencia',
        'other' => 'Otro',
    ];
    $conditionStatusLabels = [
        'active' => 'Activa',
        'controlled' => 'Controlada',
        'chronic_monitoring' => 'Monitoreo crónico',
        'resolved' => 'Resuelta',
    ];
@endphp

@section('content')
<p class="muted" style="font-size: 10px;">
    Documento oficial generado por el sistema — expediente clínico completo de la mascota a la fecha indicada.
</p>

<table class="info-table">
    <tr>
        <td>
            <div class="info-title">Mascota</div>
            <strong>{{ $pet->name }}</strong><br>
            {{ $pet->species ?? 'N/D' }} — {{ $pet->breed ?? 'N/D' }}<br>
            Sexo: {{ $pet->sex ?? 'N/D' }} | Esterilizada: {{ $pet->is_sterilized ? 'Sí' : 'No' }}<br>
            @if($pet->birth_date) Nacimiento: {{ $pet->birth_date->format('d/m/Y') }} @endif
        </td>
        <td>
            <div class="info-title">Dueño</div>
            <strong>{{ $pet->client?->full_name }}</strong><br>
            {{ $pet->client?->email ?? '' }}<br>
            {{ $pet->client?->phones->first()?->number ?? '' }}
        </td>
    </tr>
</table>

<h3 class="section-title">Alergias</h3>
@forelse($pet->allergies as $allergy)
    <p>{{ $allergy->allergen }} ({{ $allergy->allergen_type }}) — severidad: {{ $allergy->severity }} — {{ $allergy->is_active ? 'activa' : 'inactiva' }}
    @if($allergy->reaction_description) — {{ $allergy->reaction_description }} @endif</p>
@empty
    <p class="muted">Ninguna registrada.</p>
@endforelse

<h3 class="section-title">Condiciones crónicas</h3>
@forelse($pet->conditions as $condition)
    <p>{{ $condition->name }} — {{ $conditionStatusLabels[$condition->status] ?? $condition->status }}@if($condition->icd_code) ({{ $condition->icd_code }}) @endif</p>
@empty
    <p class="muted">Ninguna registrada.</p>
@endforelse

<h3 class="section-title">Vacunas</h3>
@if($pet->vaccinations->isNotEmpty())
    <table class="data-table">
        <thead><tr><th>Vacuna</th><th>Aplicada</th><th>Vigente hasta</th></tr></thead>
        <tbody>
        @foreach($pet->vaccinations as $vaccination)
            <tr>
                <td>{{ $vaccination->vaccine_name }}</td>
                <td>{{ $vaccination->applied_at?->format('d/m/Y') ?? 'N/D' }}</td>
                <td>{{ $vaccination->expires_at?->format('d/m/Y') ?? 'N/D' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="muted">Ninguna registrada.</p>
@endif

<h3 class="section-title">Historial de peso</h3>
@if($pet->weights->isNotEmpty())
    <table class="data-table">
        <thead><tr><th>Fecha</th><th>Peso (kg)</th><th>Origen</th></tr></thead>
        <tbody>
        @foreach($pet->weights as $weight)
            <tr>
                <td>{{ $weight->measured_at?->format('d/m/Y') }}</td>
                <td>{{ $weight->weight_kg }}</td>
                <td>{{ $weight->source ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="muted">Sin registros de peso.</p>
@endif

<h3 class="section-title">Adjuntos clínicos</h3>
@if($pet->attachments->isNotEmpty())
    <table class="data-table">
        <thead><tr><th>Tipo</th><th>Fecha</th><th>Descripción</th></tr></thead>
        <tbody>
        @foreach($pet->attachments as $attachment)
            <tr>
                <td>{{ $attachmentTypeLabels[$attachment->attachment_type] ?? $attachment->attachment_type }}</td>
                <td>{{ $attachment->performed_at?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $attachment->description ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="muted">Ningún adjunto registrado.</p>
@endif

<h3 class="section-title">Historial de visitas</h3>
@forelse($pet->clinicalVisits as $visit)
    <div style="border: 1px solid #ecf0f1; padding: 8px; margin-bottom: 12px;">
        <p style="margin: 0 0 4px 0;">
            <strong>{{ $visit->visited_at->format('d/m/Y H:i') }}</strong> —
            {{ $visitTypeLabels[$visit->visit_type] ?? $visit->visit_type }}
            <span class="badge">{{ $visit->status }}</span>
        </p>
        <p><strong>Atiende:</strong> {{ $visit->is_external ? ($visit->external_provider_name ?? 'Externo') : $visit->operator?->name }}</p>
        <p><strong>Motivo:</strong> {{ $visit->reason_for_visit }}</p>
        @if($visit->subjective)<p><strong>Subjetivo:</strong> {{ $visit->subjective }}</p>@endif

        <p><strong>Objetivo:</strong>
            @if($visit->weight_kg) Peso: {{ $visit->weight_kg }}kg @endif
            @if($visit->temperature_celsius) | Temp: {{ $visit->temperature_celsius }}°C @endif
            @if($visit->heart_rate_bpm) | FC: {{ $visit->heart_rate_bpm }}lpm @endif
            @if($visit->respiratory_rate_bpm) | FR: {{ $visit->respiratory_rate_bpm }}rpm @endif
            @if($visit->objective_notes) <br>{{ $visit->objective_notes }} @endif
        </p>

        @if($visit->assessment)<p><strong>Evaluación:</strong> {{ $visit->assessment }}</p>@endif
        @if($visit->diagnoses->isNotEmpty())
            <p><strong>Diagnósticos:</strong>
                @foreach($visit->diagnoses as $diagnosis)
                    {{ $diagnosis->diagnosis }} ({{ $diagnosis->diagnosis_type }}){{ !$loop->last ? ', ' : '' }}
                @endforeach
            </p>
        @endif

        @if($visit->plan)<p><strong>Plan:</strong> {{ $visit->plan }}</p>@endif
        @if($visit->prescriptions->isNotEmpty())
            <p><strong>Recetas:</strong></p>
            @foreach($visit->prescriptions as $prescription)
                <ul style="margin: 0 0 4px 0;">
                    @foreach($prescription->items as $item)
                        <li>{{ $item->drug_name }} {{ $item->concentration }} — {{ $item->dose }}, {{ $item->route }}, {{ $item->frequency }}@if($item->duration_days) por {{ $item->duration_days }} días @endif</li>
                    @endforeach
                </ul>
            @endforeach
        @endif

        @if($visit->status === 'signed')
            <p class="muted">Firmada por {{ $visit->signedBy?->name }} ({{ $visit->professional_license_snapshot }}) — {{ $visit->signed_at?->format('d/m/Y H:i') }}</p>
        @endif
    </div>
@empty
    <p class="muted">Sin visitas registradas.</p>
@endforelse
@endsection
