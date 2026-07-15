@php
    $screenDebugId = $page['screen_id'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="'Nota aclaratoria — Visita #'.$visit->id"
    :subtitle="'La visita original permanece intacta y firmada. Esta nota queda enlazada a ella.'"
/>

<div class="alert alert-info">
    Visita original: {{ $visit->visited_at->format('d/m/Y H:i') }} — {{ $visit->reason_for_visit }}
</div>

<form action="{{ route('clinical.visits.amend.store', $visit) }}" method="POST">
    @csrf

    <div class="card mb-4">
        <div class="card-body">
            <label class="form-label">Motivo de la enmienda <span class="text-danger">*</span></label>
            <textarea name="amendment_reason" class="form-control" rows="2" required placeholder="¿Qué se está corrigiendo o aclarando?"></textarea>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h3 class="h5 mb-3">Datos de la nota</h3>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha y hora</label>
                    <input type="datetime-local" name="visited_at" class="form-control" required value="{{ now()->format('Y-m-d\TH:i') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Registra (operador)</label>
                    <select name="operator_id" class="form-select" required>
                        @foreach($operators as $operator)
                            <option value="{{ $operator->id }}">{{ $operator->name }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="visit_type" value="follow_up">
                <div class="col-12">
                    <label class="form-label">Motivo (para la ficha de esta nota)</label>
                    <textarea name="reason_for_visit" class="form-control" rows="2" required>Nota aclaratoria sobre la visita #{{ $visit->id }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h3 class="h5 mb-1">Evaluación corregida</h3>
            <textarea name="assessment" class="form-control" rows="3">{{ $visit->assessment }}</textarea>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h3 class="h5 mb-1">Plan corregido</h3>
            <textarea name="plan" class="form-control" rows="3">{{ $visit->plan }}</textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Guardar nota aclaratoria (borrador)</button>
    <a href="{{ route('clinical.visits.show', $visit) }}" class="btn btn-outline-secondary">Cancelar</a>
</form>
@endsection
