@php
    $screenDebugId = 'AgEdi';
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')
@php($defaultScheduledAt = old('scheduled_at', $booking->scheduled_at?->format('Y-m-d\TH:i')))

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('agenda.show', $booking) }}" class="btn btn-outline-secondary">Volver al booking</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form action="{{ route('agenda.update', $booking) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="scheduled_at" class="form-label">Nueva fecha y hora</label>
                                <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ $defaultScheduledAt }}" class="form-control" required>
                                <div class="form-text">Solo se reprograma horario y nota. Los servicios congelados permanecen iguales.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="resource_id" class="form-label">Jaula / recurso físico</label>
                                <select id="resource_id" name="resource_id" class="form-select">
                                    <option value="">Liberar asignación</option>
                                    @foreach($resources as $resource)
                                        <option value="{{ $resource->id }}" @selected((string) old('resource_id', $assignedResourceId) === (string) $resource->id)>
                                            {{ $resource->code }} · {{ $resource->name }} · {{ $resource->branch?->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Puedes cambiar la jaula o liberar la asignación al reprogramar.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="notes" class="form-label">Notas operativas</label>
                                <textarea id="notes" name="notes" rows="4" class="form-control" placeholder="Ajustes para recepción, grooming o coordinación">{{ old('notes', $booking->notes) }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap justify-content-end">
                            <a href="{{ route('agenda.show', $booking) }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar reprogramación</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Contexto actual</h2>
                    <p class="mb-2"><strong>Mascota:</strong> {{ $pet?->name ?: 'Sin mascota' }}</p>
                    <p class="mb-2"><strong>Cliente:</strong> {{ trim(($client?->first_name ?? '') . ' ' . ($client?->last_name ?? '')) ?: 'Sin nombre visible' }}</p>
                    <p class="mb-2"><strong>Horario actual:</strong> {{ $booking->scheduled_at?->format('d/m/Y ' . (config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A')) ?: 'Sin fecha' }}</p>
                    <p class="mb-0"><strong>Servicios:</strong> {{ $booking->services->count() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection