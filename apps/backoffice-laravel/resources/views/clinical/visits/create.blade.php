@php
    $screenDebugId = $page['screen_id'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
/>

<form action="{{ route('clinical.visits.store', $pet) }}" method="POST" x-data="{ isExternal: false }">
    @csrf

    <div class="card mb-4">
        <div class="card-body">
            <h3 class="h5 mb-3">Datos de la visita</h3>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tipo de visita</label>
                    <select name="visit_type" class="form-select" required>
                        <option value="consultation">Consulta</option>
                        <option value="follow_up">Seguimiento</option>
                        <option value="emergency">Urgencia</option>
                        <option value="pre_grooming_check">Chequeo previo a grooming</option>
                        <option value="vaccination">Vacunación</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha y hora</label>
                    <input type="datetime-local" name="visited_at" class="form-control" required value="{{ now()->format('Y-m-d\TH:i') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Atiende (operador)</label>
                    <select name="operator_id" class="form-select" required>
                        @foreach($operators as $operator)
                            <option value="{{ $operator->id }}">{{ $operator->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="is_external" value="0">
                        <input class="form-check-input" type="checkbox" name="is_external" value="1" id="is-external" x-model="isExternal">
                        <label class="form-check-label" for="is-external">Atención externa (fuera de EstetiCAN)</label>
                    </div>
                </div>
                <div class="col-12" x-show="isExternal" x-cloak>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Veterinario externo</label>
                            <input type="text" name="external_provider_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cédula (si se conoce)</label>
                            <input type="text" name="external_provider_license" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Clínica externa</label>
                            <input type="text" name="external_clinic_name" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Motivo de la visita</label>
                    <textarea name="reason_for_visit" class="form-control" rows="2" required></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h3 class="h5 mb-1">Subjetivo</h3>
            <p class="text-muted small">Lo que reporta el dueño / observación general.</p>
            <textarea name="subjective" class="form-control" rows="3"></textarea>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h3 class="h5 mb-3">Objetivo</h3>
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Peso (kg)</label>
                    <input type="number" step="0.01" name="weight_kg" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Temp. (°C)</label>
                    <input type="number" step="0.1" name="temperature_celsius" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">FC (lpm)</label>
                    <input type="number" name="heart_rate_bpm" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">FR (rpm)</label>
                    <input type="number" name="respiratory_rate_bpm" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cond. corporal (1-9)</label>
                    <input type="number" min="1" max="9" name="body_condition_score" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hidratación</label>
                    <select name="hydration_status" class="form-select">
                        <option value="">—</option>
                        <option value="normal">Normal</option>
                        <option value="mild_dehydration">Deshidratación leve</option>
                        <option value="moderate_dehydration">Deshidratación moderada</option>
                        <option value="severe_dehydration">Deshidratación severa</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mucosas</label>
                    <select name="mucous_membranes" class="form-select">
                        <option value="">—</option>
                        <option value="pink">Rosadas</option>
                        <option value="pale">Pálidas</option>
                        <option value="cyanotic">Cianóticas</option>
                        <option value="icteric">Ictéricas</option>
                        <option value="congested">Congestivas</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Hallazgos de exploración física</label>
                    <textarea name="objective_notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h3 class="h5 mb-1">Evaluación</h3>
            <textarea name="assessment" class="form-control" rows="3"></textarea>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h3 class="h5 mb-3">Plan</h3>
            <textarea name="plan" class="form-control mb-3" rows="3"></textarea>
            <label class="form-label">Próxima revisión</label>
            <input type="date" name="follow_up_at" class="form-control" style="max-width: 220px;">
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Guardar borrador</button>
    <a href="{{ route('clinical.pets.show', $pet) }}" class="btn btn-outline-secondary">Cancelar</a>
</form>
@endsection
