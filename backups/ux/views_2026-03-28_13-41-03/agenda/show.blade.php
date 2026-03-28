@extends('layouts.app')

@php($breadcrumbs = $page['breadcrumbs'])

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('agenda.index') }}" class="btn btn-outline-secondary">Volver a agenda</a>
        @if($booking->status === 'scheduled')
            <a href="{{ route('agenda.edit', $booking) }}" class="btn btn-outline-warning">Reprogramar</a>
        @endif
        <a href="{{ route('pets.show', ['pet' => $pet, 'view' => 'blocks']) }}" class="btn btn-outline-dark">Ver mascota</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    <section class="catalog-overview mb-4">
        <div class="catalog-overview__grid">
            <article class="catalog-overview-card catalog-overview-card--primary">
                <span class="catalog-overview-card__eyebrow">Mascota</span>
                <div class="catalog-overview-card__value-sm">{{ $pet?->name ?: 'Sin mascota' }}</div>
                <p class="catalog-overview-card__text">{{ $pet?->species_label ?: 'Sin especie' }} @if($pet?->breed) · {{ $pet->breed }} @endif</p>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Horario</span>
                <div class="catalog-overview-card__value-sm">{{ $booking->time_window_label ?? $booking->scheduled_at?->format('d/m/Y H:i') }}</div>
                <div class="catalog-overview-card__label">{{ $booking->estimated_duration_minutes }} min estimados</div>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Estado</span>
                <div class="catalog-overview-card__value-sm">{{ $booking->status === 'scheduled' ? 'Programado' : ucfirst(str_replace('_', ' ', $booking->status)) }}</div>
                <div class="catalog-overview-card__label">${{ number_format((float) $booking->total_estimated_price, 2) }} estimados</div>
            </article>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Detalle operativo</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Cliente</dt>
                        <dd class="col-sm-8">{{ trim(($client?->first_name ?? '') . ' ' . ($client?->last_name ?? '')) ?: 'Sin nombre visible' }}</dd>

                        <dt class="col-sm-4">Fecha y hora</dt>
                        <dd class="col-sm-8">{{ $booking->scheduled_at?->format('d/m/Y H:i') ?: 'Sin fecha' }}</dd>

                        <dt class="col-sm-4">Ventana estimada</dt>
                        <dd class="col-sm-8">{{ $booking->time_window_label ?? 'Sin rango estimado' }}</dd>

                        <dt class="col-sm-4">Jaula asignada</dt>
                        <dd class="col-sm-8">
                            @php($resourceAllocation = $booking->resourceAllocations->firstWhere('allocation_type', 'reserved'))
                            @if($resourceAllocation?->resource)
                                {{ $resourceAllocation->resource->code }} · {{ $resourceAllocation->resource->name }}
                            @else
                                Sin recurso asignado.
                            @endif
                        </dd>

                        <dt class="col-sm-4">Notas</dt>
                        <dd class="col-sm-8">{{ $booking->notes ?: 'Sin notas operativas registradas.' }}</dd>

                        <dt class="col-sm-4">Motivo operativo</dt>
                        <dd class="col-sm-8">{{ $booking->cancellation_reason ?: 'Sin motivo registrado.' }}</dd>
                    </dl>

                    <hr class="my-4">

                    <h3 class="h6 text-uppercase text-body-secondary mb-3">Servicios congelados</h3>
                    <div class="agenda-service-grid">
                        @foreach($booking->services as $bookingService)
                            <article class="agenda-service-option">
                                <div class="agenda-service-option__body">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="catalog-code-pill">{{ $bookingService->service?->code ?? 'N/A' }}</div>
                                            <div class="catalog-title-stack__title mt-2">{{ $bookingService->service?->name ?? 'Servicio' }}</div>
                                        </div>
                                        <span class="catalog-type-pill">{{ strtoupper($bookingService->service?->type ?? 'SPA') }}</span>
                                    </div>
                                    <div class="agenda-service-option__meta">
                                        <span class="catalog-inline-tag">{{ (int) ($bookingService->service?->suggested_duration_minutes ?? $bookingService->service?->duration_minutes ?? 0) }} min</span>
                                    </div>
                                    <div class="agenda-service-option__price mt-3">${{ number_format((float) $bookingService->current_price, 2) }}</div>
                                    <div class="catalog-stat__hint">snapshot al programar</div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Seguimiento</h2>
                    <p class="mb-2"><strong>Servicios ejecutados ligados:</strong> {{ $booking->executedServices->count() }}</p>
                        <p class="mb-2"><strong>Bloqueos de recurso:</strong> {{ $booking->resourceAllocations->count() }}</p>
                    @if($booking->executedServices->isNotEmpty())
                        @php($executedService = $booking->executedServices->first())
                        <p class="mb-1"><strong>Ejecutado:</strong> {{ $executedService->executed_at?->format('d/m/Y H:i') }}</p>
                        <p class="mb-0"><strong>Operador:</strong> {{ $executedService->operator?->full_name ?: $executedService->operator?->name ?: 'Sin operador ligado' }}</p>
                    @else
                        <p class="text-body-secondary mb-0">Todavía no existe ejecución real ligada a este booking.</p>
                    @endif
                </div>
            </div>

            @if($booking->status === 'scheduled')
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Acciones rápidas</h2>
                        <div class="d-grid gap-2">
                            <a href="{{ route('agenda.edit', $booking) }}" class="btn btn-outline-warning">Reprogramar</a>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Cancelar booking</h2>
                        <form action="{{ route('agenda.cancel', $booking) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="cancel-reason" class="form-label">Motivo de cancelación</label>
                                <textarea id="cancel-reason" name="reason" rows="3" class="form-control" placeholder="Motivo operativo o del cliente" required>{{ old('reason') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100" data-confirm="¿Cancelar booking SPA?">Cancelar booking</button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Marcar no show</h2>
                        <form action="{{ route('agenda.no-show', $booking) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="no-show-reason" class="form-label">Nota de no show</label>
                                <textarea id="no-show-reason" name="reason" rows="3" class="form-control" placeholder="Opcional: detalle de la ausencia del cliente">{{ old('reason') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-dark w-100" data-confirm="¿Marcar este booking como no show?">Marcar no show</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection