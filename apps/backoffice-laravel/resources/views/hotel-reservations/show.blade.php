@php
    $screenDebugId = 'AgHotSho';
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')
@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('hotel-reservations.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
        <a href="{{ route('hotel-reservations.edit', $hotelReservation) }}" class="btn btn-warning border-0" style="color: #000 !important;">
            <i class="bi bi-pencil-fill me-1"></i> Editar reserva
        </a>
        @if($hotelReservation->status === 'scheduled')
            <form action="{{ route('hotel-reservations.cancel', $hotelReservation) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-danger" data-confirm="¿Cancelar reserva hotel y liberar jaula?">Cancelar reserva</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

@php($resourceAllocation = $hotelReservation->resourceAllocations->firstWhere('allocation_type', 'reserved'))

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Mascota</dt>
                    <dd class="col-sm-8">{{ $hotelReservation->pet?->name ?: 'Sin mascota' }}</dd>

                    <dt class="col-sm-4">Cliente</dt>
                    <dd class="col-sm-8">{{ $hotelReservation->pet?->client ? trim($hotelReservation->pet->client->first_name . ' ' . $hotelReservation->pet->client->last_name) : 'Sin cliente' }}</dd>

                    <dt class="col-sm-4">Inicio</dt>
                    <dd class="col-sm-8">{{ $hotelReservation->start_at?->format($datetimeFormat) }}</dd>

                    <dt class="col-sm-4">Fin</dt>
                    <dd class="col-sm-8">{{ $hotelReservation->end_at?->format($datetimeFormat) }}</dd>

                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">{{ strtoupper($hotelReservation->status) }}</dd>

                    <dt class="col-sm-4">Jaula bloqueada</dt>
                    <dd class="col-sm-8">
                        @if($resourceAllocation?->resource)
                            {{ $resourceAllocation->resource->code }} · {{ $resourceAllocation->resource->name }}
                        @else
                            Sin jaula asignada.
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Bloqueo operativo</h2>
                @if($resourceAllocation)
                    <p class="mb-2"><strong>Tipo:</strong> {{ strtoupper($resourceAllocation->allocation_type) }}</p>
                    <p class="mb-2"><strong>Ventana:</strong> {{ $resourceAllocation->starts_at?->format($datetimeFormat) }} - {{ $resourceAllocation->ends_at?->format($datetimeFormat) }}</p>
                    <p class="mb-0 text-body-secondary">En hotel, la reserva bloquea la jaula durante el rango planeado. La limpieza posterior se manejará desde la ocupación real (`stay`).</p>
                @else
                    <p class="text-muted mb-0">Esta reserva todavía no bloquea una jaula específica.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection