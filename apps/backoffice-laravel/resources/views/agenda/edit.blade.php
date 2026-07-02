@php
    $screenDebugId = 'AgSpaEdi';
    $breadcrumbs = $page['breadcrumbs'];
    $selectedServiceIds = old('services', $booking->services->pluck('service_id')->map(fn($id) => (string)$id)->toArray());
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
        <a href="{{ route('agenda.show', $booking) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver al detalle
        </a>
    </x-slot:actions>
</x-page-header>

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="catalog-content-wide">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 text-uppercase text-body-secondary fw-bold mb-0">Datos de la Cita</h2>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('agenda.update', $booking) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="operator_id" class="form-label fw-semibold">Operador</label>
                                <select id="operator_id" name="operator_id" class="form-select" required>
                                    <option value="">Selecciona un operador…</option>
                                    @foreach($operators as $operator)
                                        <option value="{{ $operator->id }}"
                                            @selected((string) old('operator_id', $booking->operator_id) === (string) $operator->id)>
                                            {{ $operator->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="scheduled_at" class="form-label fw-semibold">Fecha y hora</label>
                                <div id="scheduled_at_wrapper" class="{{ old('operator_id', $booking->operator_id) ? '' : 'is-locked' }}" style="position:relative;">
                                    <input id="scheduled_at" type="datetime-local" name="scheduled_at"
                                           value="{{ $defaultScheduledAt }}" class="form-control" required
                                           data-force-24h="1"
                                           data-min-time="{{ $openingTime }}"
                                           data-max-time="{{ $closingTime }}">
                                </div>
                                <div class="form-text">Horario operativo: {{ $openingTime }}–{{ $closingTime }}.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="resource_id" class="form-label fw-semibold">Jaula / recurso físico</label>
                                <select id="resource_id" name="resource_id" class="form-select">
                                    <option value="">Sin asignar</option>
                                    @foreach($resources as $resource)
                                        <option value="{{ $resource->id }}"
                                            @selected((string) old('resource_id', $assignedResourceId) === (string) $resource->id)>
                                            {{ $resource->code }} · {{ $resource->name }}{{ $resource->administrative_status !== 'active' ? ' (inactivo)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="notes" class="form-label fw-semibold">Notas operativas</label>
                                <textarea id="notes" name="notes" rows="4" class="form-control"
                                    placeholder="Ajustes para recepción, grooming o coordinación">{{ old('notes', $booking->notes) }}</textarea>
                            </div>
                        </div>

                        @if($booking->status === 'scheduled')
                        <hr>
                        <h6 class="text-uppercase small text-body-secondary fw-bold mb-3">Servicios</h6>
                        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-2 mb-4">
                            @foreach($services as $service)
                                @php($checked = in_array((string)$service->id, $selectedServiceIds, true))
                                <div class="col">
                                    <label class="card h-100 border-2 {{ $checked ? 'border-primary bg-primary-subtle' : 'border' }}"
                                           style="cursor:pointer;">
                                        <div class="card-body p-2 d-flex align-items-start gap-2">
                                            <input type="checkbox" name="services[]" value="{{ $service->id }}"
                                                   class="form-check-input mt-1 flex-shrink-0" @checked($checked)>
                                            <div>
                                                <div class="fw-semibold small lh-sm">{{ $service->name }}</div>
                                                <div class="text-muted" style="font-size:0.7rem;">${{ number_format($service->price ?? 0, 2) }}</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @else
                        <hr>
                        <h6 class="text-uppercase small text-body-secondary fw-bold mb-2">Servicios (no editables en este estado)</h6>
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            @foreach($booking->services as $bs)
                                <span class="badge bg-light text-dark border">{{ $bs->service?->name ?? 'Servicio' }}</span>
                            @endforeach
                        </div>
                        @endif

                        <div class="d-flex gap-2 flex-wrap justify-content-end">
                            <a href="{{ route('agenda.show', $booking) }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary fw-bold">
                                <i class="bi bi-check2 me-1"></i> Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h2 class="h6 text-uppercase text-body-secondary fw-bold mb-3">Resumen de la cita</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-body-secondary">Mascota</dt>
                        <dd class="col-7 fw-semibold">{{ $pet?->name ?: '—' }}</dd>

                        <dt class="col-5 text-body-secondary">Cliente</dt>
                        <dd class="col-7">{{ trim(($client?->first_name ?? '') . ' ' . ($client?->last_name ?? '')) ?: '—' }}</dd>

                        <dt class="col-5 text-body-secondary">Estado</dt>
                        <dd class="col-7">
                            <span class="badge text-bg-{{ match($booking->status) {
                                'scheduled' => 'secondary',
                                'work_order' => 'warning',
                                'completed' => 'success',
                                default => 'light'
                            } }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                        </dd>

                        <dt class="col-5 text-body-secondary">Programada</dt>
                        <dd class="col-7">{{ $booking->scheduled_at?->format($datetimeFormat) ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    #scheduled_at_wrapper.is-locked {
        opacity: .5;
        pointer-events: none;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var operatorSelect = document.getElementById('operator_id');
        var wrapper = document.getElementById('scheduled_at_wrapper');
        if (!operatorSelect || !wrapper) return;

        function syncScheduledAtState() {
            wrapper.classList.toggle('is-locked', !operatorSelect.value);
        }

        operatorSelect.addEventListener('change', syncScheduledAtState);
        syncScheduledAtState();
    });
</script>
@endpush
@endsection
