@php
    $screenDebugId = 'SerSho';

    $page = \App\Support\Pages\ServicesPage::show($service);
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
        <a href="{{ route('services.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
        <a href="{{ route('services.edit', $service) }}" class="btn btn-secondary">Editar servicio</a>
        <form action="{{ route('services.duplicate', $service) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-dark">Duplicar servicio</button>
        </form>
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Código</dt>
                    <dd class="col-sm-8">{{ $service->code }}</dd>

                    <dt class="col-sm-4">Tipo</dt>
                    <dd class="col-sm-8">{{ strtoupper($service->type) }}</dd>

                    <dt class="col-sm-4">Tipo de operador</dt>
                    <dd class="col-sm-8">
                        @if($service->operatorRole)
                            {{ $service->operatorRole->name }} ({{ $service->operatorRole->code }})
                        @else
                            Sin tipo ligado.
                        @endif
                    </dd>

                    <dt class="col-sm-4">Descripción base</dt>
                    <dd class="col-sm-8">{{ $service->description ?: 'Sin descripción base.' }}</dd>

                    <dt class="col-sm-4">Precio sugerido</dt>
                    <dd class="col-sm-8">${{ number_format((float) $service->suggested_price, 2) }}</dd>

                    <dt class="col-sm-4">Duración sugerida</dt>
                    <dd class="col-sm-8">{{ $service->suggested_duration_minutes }} minutos</dd>

                    <dt class="col-sm-4">Recurrencia</dt>
                    <dd class="col-sm-8">{{ $service->recurrence_days ? 'Cada ' . $service->recurrence_days . ' días' : 'No aplica' }}</dd>

                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">{{ $service->is_active ? 'Activo' : 'Inactivo' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5">Uso histórico</h2>
                <p class="display-6 mb-1">{{ $service->executed_service_items_count }}</p>
                <p class="text-body-secondary mb-0">Veces referenciado en detalle histórico de servicios ejecutados.</p>
            </div>
        </div>
    </div>
</div>
@endsection