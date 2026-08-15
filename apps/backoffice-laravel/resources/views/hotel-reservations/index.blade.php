@php
    $screenDebugId = 'AgHotInd';
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
        <a href="{{ route('hotel-reservations.create') }}" class="btn btn-primary">Nueva reserva hotel</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        @if($reservations->isEmpty())
            <p class="text-muted mb-0">Todavía no hay reservas hotel registradas.</p>
        @else
            <div class="list-group list-group-flush">
                @foreach($reservations as $reservation)
                    @php($resourceAllocation = $reservation->resourceAllocations->firstWhere('allocation_type', 'reserved'))
                    <a href="{{ route('hotel-reservations.show', $reservation) }}" class="list-group-item list-group-item-action px-0">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold">{{ $reservation->pet?->name ?: 'Mascota sin nombre' }}</div>
                                <div class="small text-body-secondary">{{ $reservation->pet?->client ? trim($reservation->pet->client->first_name . ' ' . $reservation->pet->client->last_name) : 'Sin cliente' }}</div>
                                <div class="small text-body-secondary mt-1">{{ $reservation->start_at?->format($datetimeFormat) }} - {{ $reservation->end_at?->format($datetimeFormat) }}</div>
                            </div>
                            <div class="text-end small text-body-secondary">
                                <div>{{ match ($reservation->status) {
                                    'scheduled' => 'Programada',
                                    'cancelled' => 'Cancelada',
                                    'completed' => 'Completada',
                                    default => ucfirst($reservation->status),
                                } }}</div>
                                <div>{{ $resourceAllocation?->resource ? $resourceAllocation->resource->code . ' · ' . $resourceAllocation->resource->name : 'Sin jaula' }}</div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-3">{{ $reservations->links() }}</div>
        @endif
    </div>
</div>
@endsection