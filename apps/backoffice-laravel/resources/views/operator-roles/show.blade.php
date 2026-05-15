@php
    $screenDebugId = 'OprRolSho';
    use App\Support\Pages\OperatorRolesPage;

    $page = OperatorRolesPage::show($operatorRole);
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
        <a href="{{ route('operator-roles.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
        <a href="{{ route('operator-roles.edit', $operatorRole) }}" class="btn btn-secondary">Editar tipo</a>
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Clave</dt>
                    <dd class="col-sm-8">{{ $operatorRole->code }}</dd>

                    <dt class="col-sm-4">Descripción</dt>
                    <dd class="col-sm-8">{{ $operatorRole->description ?: 'Sin descripción.' }}</dd>

                    <dt class="col-sm-4">Costo base/hora</dt>
                    <dd class="col-sm-8">
                        @if($operatorRole->default_hourly_rate !== null)
                            ${{ number_format((float) $operatorRole->default_hourly_rate, 2) }}
                        @else
                            Sin tarifa base
                        @endif
                    </dd>

                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">{{ $operatorRole->is_active ? 'Activo' : 'Inactivo' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5">Uso actual</h2>
                <p class="display-6 mb-1">{{ $operatorRole->assignments_count }}</p>
                <p class="text-body-secondary mb-0">Asignaciones registradas sobre operadores.</p>
            </div>
        </div>
    </div>
</div>
@endsection