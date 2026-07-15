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
            <a href="{{ route('clinical.pets.folder', $pet) }}" target="_blank" class="btn btn-outline-secondary">Ficha imprimible</a>
        </div>
    </x-slot:actions>
</x-page-header>

{{-- Peso --}}
<section id="weights" class="mb-5">
    <h2 class="h4 mb-3">Peso</h2>
    <div class="card">
        <div class="card-body">
            @if($pet->weights->isEmpty())
                <p class="text-muted mb-0">Sin registros de peso.</p>
            @else
                <table class="table table-sm mb-0">
                    <thead><tr><th>Fecha</th><th>Peso (kg)</th><th>Origen</th></tr></thead>
                    <tbody>
                        @foreach($pet->weights as $weight)
                            <tr>
                                <td>{{ $weight->measured_at->format('d/m/Y') }}</td>
                                <td>{{ $weight->weight_kg }}</td>
                                <td>{{ ['clinical_visit' => 'Visita clínica', 'grooming_checkin' => 'Check-in grooming', 'manual' => 'Manual'][$weight->source] ?? $weight->source }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</section>

{{-- Alergias --}}
<section id="allergies" class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Alergias</h2>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <h3 class="h6">Nueva alergia</h3>
            <form action="{{ route('clinical.allergies.store', $pet) }}" method="POST" class="row g-3 mt-1">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Alergeno</label>
                    <input type="text" name="allergen" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="allergen_type" class="form-select" required>
                        <option value="food">Alimento</option>
                        <option value="medication">Medicamento</option>
                        <option value="environmental">Ambiental</option>
                        <option value="flea_tick">Pulgas/garrapatas</option>
                        <option value="other">Otro</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Severidad</label>
                    <select name="severity" class="form-select" required>
                        <option value="mild">Leve</option>
                        <option value="moderate">Moderada</option>
                        <option value="severe">Severa</option>
                        <option value="anaphylaxis">Anafilaxia</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="allergy-active-create" checked>
                        <label class="form-check-label" for="allergy-active-create">Activa</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Reacción observada</label>
                    <textarea name="reaction_description" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Registrar alergia</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            @if($pet->allergies->isEmpty())
                <p class="text-muted mb-0">Sin alergias registradas.</p>
            @else
                @foreach($pet->allergies as $allergy)
                    <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                        <div>
                            <span class="fw-semibold">{{ $allergy->allergen }}</span>
                            <span class="badge text-bg-{{ $allergy->severity === 'anaphylaxis' || $allergy->severity === 'severe' ? 'danger' : 'warning' }} ms-2">{{ $allergy->severity }}</span>
                            @if(!$allergy->is_active) <span class="badge text-bg-secondary ms-1">Inactiva</span> @endif
                            @if($allergy->reaction_description) <p class="text-muted small mb-0">{{ $allergy->reaction_description }}</p> @endif
                        </div>
                        <form action="{{ route('clinical.allergies.destroy', [$pet, $allergy]) }}" method="POST" onsubmit="return confirm('¿Eliminar esta alergia?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>

{{-- Condiciones crónicas --}}
<section id="conditions" class="mb-5">
    <h2 class="h4 mb-3">Condiciones crónicas (problem list)</h2>
    <div class="card mb-3">
        <div class="card-body">
            <h3 class="h6">Nueva condición</h3>
            <form action="{{ route('clinical.conditions.store', $pet) }}" method="POST" class="row g-3 mt-1">
                @csrf
                <div class="col-md-5">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estado</label>
                    <select name="status" class="form-select" required>
                        <option value="active">Activa</option>
                        <option value="controlled">Controlada</option>
                        <option value="chronic_monitoring">Monitoreo crónico</option>
                        <option value="resolved">Resuelta</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Inicio</label>
                    <input type="date" name="onset_date" class="form-control">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Registrar condición</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            @if($pet->conditions->isEmpty())
                <p class="text-muted mb-0">Sin condiciones registradas.</p>
            @else
                @foreach($pet->conditions as $condition)
                    <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                        <div>
                            <span class="fw-semibold">{{ $condition->name }}</span>
                            <span class="badge text-bg-info ms-2">{{ $condition->status }}</span>
                            @if($condition->promoted_from_diagnosis_id)
                                <span class="badge text-bg-light border ms-1">Promovida desde diagnóstico</span>
                            @endif
                        </div>
                        <form action="{{ route('clinical.conditions.destroy', [$pet, $condition]) }}" method="POST" onsubmit="return confirm('¿Eliminar esta condición?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>

{{-- Vacunas --}}
<section id="vaccinations" class="mb-5">
    <h2 class="h4 mb-3">Vacunas</h2>
    <div class="card mb-3" x-data="{ showNewItem: false, isExternal: false }">
        <div class="card-body">
            <h3 class="h6">Nueva vacuna</h3>
            <form action="{{ route('clinical.vaccinations.store', $pet) }}" method="POST" class="row g-3 mt-1">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Vacuna</label>
                    <select name="service_id" class="form-select" required>
                        <option value="">Seleccionar</option>
                        @foreach($vaccineServices as $vaccineService)
                            <option value="{{ $vaccineService->id }}">{{ $vaccineService->name }}</option>
                        @endforeach
                    </select>
                    @if($vaccineServices->isEmpty())
                        <div class="form-text text-danger">No hay servicios tipo "Vacuna" activos — <a href="{{ route('services.create') }}">crea uno primero</a>.</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label">Artículo (producto/marca)</label>
                    <div class="d-flex gap-2">
                        <select name="item_id" class="form-select">
                            <option value="">Sin especificar</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}@if($item->brand) — {{ $item->brand }}@endif @if($item->presentation) ({{ $item->presentation }})@endif</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-outline-secondary text-nowrap" @click="showNewItem = !showNewItem">+ Nuevo</button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Aplicada</label>
                    <input type="date" name="applied_at" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Vigente hasta</label>
                    <input type="date" name="expires_at" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Lote</label>
                    <input type="text" name="lot_number" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fabricante</label>
                    <input type="text" name="manufacturer" class="form-control" placeholder="Se auto-llena si eliges un artículo">
                </div>
                <div class="col-md-9 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="is_external" value="0">
                        <input class="form-check-input" type="checkbox" name="is_external" value="1" id="vaccine-external" x-model="isExternal">
                        <label class="form-check-label" for="vaccine-external">Aplicada externamente (otro veterinario, campaña, etc.)</label>
                    </div>
                </div>
                <div class="col-12" x-show="isExternal" x-cloak>
                    <div class="alert alert-info mb-0 py-2">Se registra igual en el expediente (la mascota queda protegida y no dispara advertencias), pero no genera ningún cargo ni descuento de inventario.</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Registrar vacuna</button>
                </div>
            </form>

            <div x-show="showNewItem" x-cloak class="border-top mt-3 pt-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="h6 mb-0">Nuevo artículo (alta rápida al maestro — sin existencia ni transacciones)</h4>
                    @can('ver catalogo_articulos')
                        <a href="{{ route('items.index') }}" class="small">Administrar catálogo de artículos</a>
                    @endcan
                </div>
                <form action="{{ route('items.store') }}" method="POST" class="row g-2 mt-1">
                    @csrf
                    <input type="hidden" name="return_to_pet" value="{{ $pet->id }}">
                    <div class="col-md-4">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Nombre del artículo" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="brand" class="form-control form-control-sm" placeholder="Marca">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="presentation" class="form-control form-control-sm" placeholder="Presentación">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">Crear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            @if($pet->vaccinations->isEmpty())
                <p class="text-muted mb-0">Sin vacunas registradas.</p>
            @else
                <table class="table table-sm mb-0">
                    <thead><tr><th>Vacuna</th><th>Aplicada</th><th>Vigente hasta</th><th></th></tr></thead>
                    <tbody>
                        @foreach($pet->vaccinations as $vaccination)
                            <tr>
                                <td>
                                    {{ $vaccination->vaccine_name }}
                                    @if($vaccination->is_external)
                                        <span class="badge text-bg-info ms-1">Externa</span>
                                    @endif
                                </td>
                                <td>{{ $vaccination->applied_at?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    {{ $vaccination->expires_at?->format('d/m/Y') ?? '—' }}
                                    @if($vaccination->isExpired())
                                        <span class="badge text-bg-danger ms-1">Vencida</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form action="{{ route('clinical.vaccinations.destroy', [$pet, $vaccination]) }}" method="POST" onsubmit="return confirm('¿Eliminar esta vacuna?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</section>

@php
    $visitTypeLabels = [
        'consultation' => 'Consulta',
        'follow_up' => 'Seguimiento',
        'emergency' => 'Urgencia',
        'pre_grooming_check' => 'Chequeo previo a grooming',
        'vaccination' => 'Vacunación',
    ];
    $visitStatusLabels = [
        'draft' => 'Borrador',
        'signed' => 'Firmada',
        'amended' => 'Corregida (ver nota aclaratoria)',
    ];
@endphp

{{-- Historial de visitas --}}
<section id="visits" class="mb-5">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h2 class="h4 mb-1">Historial de visitas</h2>
            <p class="text-muted mb-0">Cada fila es un evento — una consulta que ocurrió en una fecha. Clic en "Ver" para abrir el detalle completo (SOAP, diagnósticos, receta, firma).</p>
        </div>
        <a href="{{ route('clinical.visits.create', $pet) }}" class="btn btn-primary text-nowrap">Nueva visita</a>
    </div>
    <div class="card">
        <div class="card-body">
            @if($pet->clinicalVisits->isEmpty())
                <p class="text-muted mb-0">Sin visitas registradas.</p>
            @else
                <table class="table table-sm mb-0">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Atendió</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @foreach($pet->clinicalVisits as $visit)
                            <tr>
                                <td>{{ $visit->visited_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $visitTypeLabels[$visit->visit_type] ?? $visit->visit_type }}</td>
                                <td>{{ $visit->is_external ? ($visit->external_provider_name ?? 'Externo') : $visit->operator?->name }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $visit->status === 'signed' ? 'success' : ($visit->status === 'amended' ? 'secondary' : 'warning') }}">{{ $visitStatusLabels[$visit->status] ?? $visit->status }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('clinical.visits.show', $visit) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</section>
@endsection
