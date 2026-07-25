@php
    $screenDebugId = $page['screen_id'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            @if($visit->status === 'draft')
                <a href="{{ route('clinical.visits.edit', $visit) }}" class="btn btn-outline-secondary">Editar</a>
                @if(!$visit->is_external)
                    <form action="{{ route('clinical.visits.sign', $visit) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success" data-confirm="¿Firmar esta visita? Quedará inmutable.">Firmar</button>
                    </form>
                @endif
            @elseif($visit->status === 'signed')
                <a href="{{ route('clinical.visits.amend.create', $visit) }}" class="btn btn-outline-warning">Nota aclaratoria</a>
            @endif
        </div>
    </x-slot:actions>
</x-page-header>

<div class="d-flex gap-2 mb-3">
    <span class="badge text-bg-{{ $visit->status === 'signed' ? 'success' : ($visit->status === 'amended' ? 'secondary' : 'warning') }}">{{ $visit->status }}</span>
    @if($visit->is_external)
        <span class="badge text-bg-info">Atención externa</span>
    @endif
</div>

@if($visit->amendsVisit)
    <div class="alert alert-info">
        Esta es una nota aclaratoria de la <a href="{{ route('clinical.visits.show', $visit->amendsVisit) }}">visita #{{ $visit->amendsVisit->id }}</a>.
        @if($visit->amendment_reason) <br>Motivo: {{ $visit->amendment_reason }} @endif
    </div>
@endif

@if($visit->amendments->isNotEmpty())
    <div class="alert alert-warning">
        Esta visita tiene {{ $visit->amendments->count() }} nota(s) aclaratoria(s):
        @foreach($visit->amendments as $amendment)
            <a href="{{ route('clinical.visits.show', $amendment) }}">Ver nota #{{ $amendment->id }} →</a>
        @endforeach
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <h3 class="h5 mb-3">Datos de la visita</h3>
        <dl class="row mb-0">
            <dt class="col-sm-3">Mascota</dt><dd class="col-sm-9">{{ $visit->pet->name }}</dd>
            <dt class="col-sm-3">Tipo</dt><dd class="col-sm-9">{{ $visit->visit_type }}</dd>
            <dt class="col-sm-3">Fecha</dt><dd class="col-sm-9">{{ $visit->visited_at->format('d/m/Y H:i') }}</dd>
            <dt class="col-sm-3">Atiende</dt><dd class="col-sm-9">{{ $visit->is_external ? ($visit->external_provider_name ?? 'Externo') : $visit->operator?->name }}</dd>
            @if($visit->is_external)
                <dt class="col-sm-3">Clínica externa</dt><dd class="col-sm-9">{{ $visit->external_clinic_name ?? '—' }}</dd>
                <dt class="col-sm-3">Cédula externa</dt><dd class="col-sm-9">{{ $visit->external_provider_license ?? '—' }}</dd>
            @endif
            <dt class="col-sm-3">Motivo</dt><dd class="col-sm-9">{{ $visit->reason_for_visit }}</dd>
            @if($visit->status === 'signed')
                <dt class="col-sm-3">Firmada por</dt><dd class="col-sm-9">{{ $visit->signedBy?->name }} ({{ $visit->professional_license_snapshot }}) — {{ $visit->signed_at?->format('d/m/Y H:i') }}</dd>
            @endif
        </dl>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h3 class="h5">Subjetivo</h3>
        <p>{{ $visit->subjective ?: '—' }}</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h3 class="h5 mb-3">Objetivo</h3>
        <dl class="row mb-0">
            <dt class="col-sm-3">Peso</dt><dd class="col-sm-9">{{ $visit->weight_kg ?? '—' }} kg</dd>
            <dt class="col-sm-3">Temperatura</dt><dd class="col-sm-9">{{ $visit->temperature_celsius ?? '—' }} °C</dd>
            <dt class="col-sm-3">FC</dt><dd class="col-sm-9">{{ $visit->heart_rate_bpm ?? '—' }} lpm</dd>
            <dt class="col-sm-3">FR</dt><dd class="col-sm-9">{{ $visit->respiratory_rate_bpm ?? '—' }} rpm</dd>
            <dt class="col-sm-3">Mucosas</dt><dd class="col-sm-9">{{ $visit->mucous_membranes ?? '—' }}</dd>
            <dt class="col-sm-3">Hidratación</dt><dd class="col-sm-9">{{ $visit->hydration_status ?? '—' }}</dd>
            <dt class="col-sm-3">Cond. corporal</dt><dd class="col-sm-9">{{ $visit->body_condition_score ?? '—' }}/9</dd>
            <dt class="col-sm-3">Hallazgos</dt><dd class="col-sm-9">{{ $visit->objective_notes ?: '—' }}</dd>
        </dl>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h3 class="h5">Evaluación</h3>
        <p>{{ $visit->assessment ?: '—' }}</p>

        <h4 class="h6 mt-4">Diagnósticos</h4>
        @foreach($visit->diagnoses as $diagnosis)
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                    <span class="fw-semibold">{{ $diagnosis->diagnosis }}</span>
                    <span class="badge text-bg-light border">{{ $diagnosis->diagnosis_type }}</span>
                </div>
                @if(!$diagnosis->promoted_to_condition_id)
                    <form action="{{ route('clinical.diagnoses.promote', $diagnosis) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-info">Promover a condición crónica</button>
                    </form>
                @else
                    <span class="badge text-bg-secondary">Ya promovido</span>
                @endif
            </div>
        @endforeach

        @if($visit->status === 'draft')
            <form action="{{ route('clinical.diagnoses.store', $visit) }}" method="POST" class="row g-2 mt-3">
                @csrf
                <div class="col-md-4"><input type="text" name="diagnosis" class="form-control" placeholder="Diagnóstico" required></div>
                <div class="col-md-3">
                    <select name="diagnosis_type" class="form-select">
                        <option value="presumptive">Presuntivo</option>
                        <option value="definitive">Definitivo</option>
                        <option value="differential">Diferencial</option>
                        <option value="ruled_out">Descartado</option>
                    </select>
                </div>
                <div class="col-md-3"><input type="text" name="notes" class="form-control" placeholder="Notas"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100">Agregar</button></div>
            </form>
        @endif
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h3 class="h5">Plan</h3>
        <p>{{ $visit->plan ?: '—' }}</p>
        @if($visit->follow_up_at)
            <p class="text-muted">Próxima revisión: {{ $visit->follow_up_at->format('d/m/Y') }}</p>
        @endif

        <h4 class="h6 mt-4">Recetas</h4>
        @forelse($visit->prescriptions as $prescription)
            <div class="border-bottom py-2">
                <ul class="mb-1">
                    @foreach($prescription->items as $item)
                        <li>{{ $item->drug_name }} {{ $item->concentration }} — {{ $item->dose }}, {{ $item->route }}, {{ $item->frequency }}@if($item->duration_days) por {{ $item->duration_days }} días @endif</li>
                    @endforeach
                </ul>
                @if($prescription->general_instructions)
                    <p class="text-muted small mb-0">{{ $prescription->general_instructions }}</p>
                @endif
                <a href="{{ route('clinical.prescriptions.pdf', $prescription) }}" class="btn btn-sm btn-outline-primary mt-1">Imprimir receta (PDF)</a>
            </div>
        @empty
            <p class="text-muted">Sin recetas.</p>
        @endforelse

        @if($visit->status === 'draft')
            <form action="{{ route('clinical.prescriptions.store', $visit) }}" method="POST" class="mt-3">
                @csrf
                <h5 class="h6">Nueva receta</h5>
                <div class="row g-2 mb-2" x-data="{
                    pharmacyItems: {{ \Illuminate\Support\Js::from($pharmacyItems->map(fn ($i) => ['id' => (string) $i->id, 'name' => $i->name, 'presentation' => $i->presentation])->values()) }},
                }">
                    <div class="col-md-3">
                        <select name="items[0][item_id]" class="form-select mb-1" @change="
                            const item = pharmacyItems.find(i => i.id === $event.target.value);
                            if (item) { $refs.drugName.value = item.name; $refs.concentration.value = item.presentation || ''; }
                        ">
                            <option value="">Sin especificar (farmacia)</option>
                            @foreach($pharmacyItems as $pharmacyItem)
                                <option value="{{ $pharmacyItem->id }}">{{ $pharmacyItem->name }}@if($pharmacyItem->presentation) ({{ $pharmacyItem->presentation }})@endif</option>
                            @endforeach
                        </select>
                        <input type="text" name="items[0][drug_name]" x-ref="drugName" class="form-control" placeholder="Fármaco" required>
                        <div class="form-text">El selector solo lista artículos con departamento "Farmacia"; el nombre se puede escribir/editar libre.</div>
                    </div>
                    <div class="col-md-2"><input type="text" name="items[0][concentration]" x-ref="concentration" class="form-control" placeholder="Concentración"></div>
                    <div class="col-md-2"><input type="text" name="items[0][dose]" class="form-control" placeholder="Dosis" required></div>
                    <div class="col-md-2">
                        <select name="items[0][route]" class="form-select">
                            <option value="oral">Oral</option>
                            <option value="topical">Tópica</option>
                            <option value="subcutaneous">Subcutánea</option>
                            <option value="intramuscular">Intramuscular</option>
                            <option value="intravenous">Intravenosa</option>
                            <option value="ophthalmic">Oftálmica</option>
                            <option value="otic">Ótica</option>
                            <option value="other">Otra</option>
                        </select>
                    </div>
                    <div class="col-md-2"><input type="text" name="items[0][frequency]" class="form-control" placeholder="Frecuencia" required></div>
                    <div class="col-md-1"><input type="number" name="items[0][duration_days]" class="form-control" placeholder="Días"></div>
                </div>
                <textarea name="general_instructions" class="form-control mb-2" rows="2" placeholder="Instrucciones generales"></textarea>
                <button type="submit" class="btn btn-outline-primary">Guardar receta</button>
            </form>
        @endif
    </div>
</div>

<a href="{{ route('clinical.pets.show', $visit->pet) }}" class="btn btn-outline-secondary">← Volver al expediente</a>
@endsection
