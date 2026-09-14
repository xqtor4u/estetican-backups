@php
    $screenDebugId = 'OprRolEdi';

    $page = \App\Support\Pages\OperatorRolesPage::edit($operatorRole);
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
        <a href="{{ route('operator-roles.show', $operatorRole) }}" class="btn btn-outline-secondary">Ver detalle</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form action="{{ route('operator-roles.update', $operatorRole) }}" method="POST">
            @csrf
            @method('PUT')
            @include('operator-roles.partials.form', ['submitLabel' => 'Actualizar tipo'])
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h2 class="h6 mb-0">Servicios de la plantilla</h2>
        <p class="small text-muted mb-0">
            Servicios que trae por defecto quien tenga este rol. Al asignar el rol a un operador,
            estos servicios entran a su lista de capacidades sin marcarlos uno por uno. Cada operador
            puede después quitarse o agregarse servicios sueltos desde su ficha.
        </p>
    </div>
    <div class="card-body">
        <form action="{{ route('operator-roles.template.update', $operatorRole) }}" method="POST">
            @csrf
            @method('PUT')

            @if($templateServices->isEmpty())
                <x-empty-state title="No hay servicios activos en el catálogo." />
            @else
                @foreach($templateServices->groupBy('type') as $type => $group)
                    <fieldset class="mb-3">
                        <legend class="h6 text-uppercase text-muted small mb-2">{{ $type ?: 'Sin tipo' }}</legend>
                        <div class="row g-2">
                            @foreach($group as $service)
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="template_service_ids[]"
                                               value="{{ $service->id }}"
                                               id="tpl-svc-{{ $service->id }}"
                                               @checked(in_array($service->id, old('template_service_ids', $templateServiceIds), true))>
                                        <label class="form-check-label" for="tpl-svc-{{ $service->id }}">{{ $service->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <button type="submit" class="btn btn-primary">Guardar plantilla</button>
            @endif
        </form>
    </div>
</div>
@endsection