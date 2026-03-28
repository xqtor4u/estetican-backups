@extends('layouts.app')

@php
    use App\Support\Pages\BranchesPage;

    $page = BranchesPage::show($branch);
    $breadcrumbs = $page['breadcrumbs'];
@endphp

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        @if($branch->google_maps_url)
            <a href="{{ $branch->google_maps_url }}" class="btn btn-outline-dark" target="_blank" rel="noopener noreferrer">Abrir en Maps</a>
        @endif
        @if($branch->whats_app_share_url)
            <a href="{{ $branch->whats_app_share_url }}" class="btn btn-outline-success" target="_blank" rel="noopener noreferrer">Compartir por WhatsApp</a>
        @endif
        <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
        <a href="{{ route('branches.edit', $branch) }}" class="btn btn-secondary">Editar sucursal</a>
        <form action="{{ route('branches.destroy', $branch) }}" method="POST" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger" data-confirm="¿Eliminar sucursal? Esta acción no se puede deshacer.">Eliminar sucursal</button>
        </form>
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Clave</dt>
                    <dd class="col-sm-8">{{ $branch->code }}</dd>

                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8">{{ $branch->name }}</dd>

                    <dt class="col-sm-4">Dirección</dt>
                    <dd class="col-sm-8">{{ $branch->formatted_address !== '' ? $branch->formatted_address : 'Sin dirección capturada.' }}</dd>

                    <dt class="col-sm-4">Lat / Lng</dt>
                    <dd class="col-sm-8">
                        @if($branch->lat !== null && $branch->lng !== null)
                            {{ $branch->lat }}, {{ $branch->lng }}
                        @else
                            Sin coordenadas.
                        @endif
                    </dd>

                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">{{ $branch->is_active ? 'Activa' : 'Inactiva' }}</dd>

                    <dt class="col-sm-4">Notas</dt>
                    <dd class="col-sm-8">{{ $branch->notes ?: 'Sin notas operativas.' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Operadores asignados</h2>
                    <span class="badge text-bg-light">{{ $branch->operator_assignments_count }} total</span>
                </div>

                @if($branch->operatorAssignments->isEmpty())
                    <p class="text-body-secondary mb-0">Todavía no hay operadores ligados a esta base.</p>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($branch->operatorAssignments as $assignment)
                            <div class="list-group-item px-0">
                                <div class="fw-semibold">{{ $assignment->operator?->full_name ?: $assignment->operator?->name ?: 'Operador sin nombre' }}</div>
                                <div class="small text-body-secondary">
                                    {{ $assignment->operator?->code ?: 'Sin clave' }}
                                    @if($assignment->is_primary)
                                        · Base principal
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection