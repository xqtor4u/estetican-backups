@extends('layouts.pdf')

@section('title', 'Receta médica — ' . $prescription->clinicalVisit->pet->name)
@section('document-type', 'Receta Médica Veterinaria')

@section('content')
@php $pet = $prescription->clinicalVisit->pet; @endphp

<table class="info-table">
    <tr>
        <td>
            <div class="info-title">Mascota</div>
            <strong>{{ $pet->name }}</strong><br>
            {{ $pet->species ?? 'N/D' }} — {{ $pet->breed ?? 'N/D' }}
        </td>
        <td>
            <div class="info-title">Dueño</div>
            <strong>{{ $pet->client?->full_name }}</strong><br>
            {{ $pet->client?->phones->first()?->number ?? '' }}
        </td>
    </tr>
</table>

<p>
    <strong>Prescrita por:</strong> {{ $prescription->prescribedBy?->name ?? '—' }}
    @if($prescription->prescribedBy?->professional_license) (Cédula: {{ $prescription->prescribedBy->professional_license }}) @endif
    <br>
    <strong>Fecha:</strong> {{ $prescription->prescribed_at?->format('d/m/Y') }}
</p>

<h3 class="section-title">Indicaciones</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>Fármaco</th>
            <th>Concentración</th>
            <th>Dosis</th>
            <th>Vía</th>
            <th>Frecuencia</th>
            <th>Duración</th>
        </tr>
    </thead>
    <tbody>
        @foreach($prescription->items as $item)
            <tr>
                <td>{{ $item->drug_name }}</td>
                <td>{{ $item->concentration ?? '—' }}</td>
                <td>{{ $item->dose }}</td>
                <td>{{ $item->route }}</td>
                <td>{{ $item->frequency }}</td>
                <td>{{ $item->duration_days ? $item->duration_days . ' días' : '—' }}</td>
            </tr>
            @if($item->special_instructions)
                <tr><td colspan="6" class="muted">{{ $item->special_instructions }}</td></tr>
            @endif
        @endforeach
    </tbody>
</table>

@if($prescription->general_instructions)
    <h3 class="section-title">Instrucciones generales</h3>
    <p>{{ $prescription->general_instructions }}</p>
@endif

<table class="signature-table">
    <tr>
        <td>Firma del médico veterinario</td>
    </tr>
</table>
@endsection
