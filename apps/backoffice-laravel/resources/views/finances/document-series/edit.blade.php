@php($screenDebugId = 'FinDsEdt')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas / Series de documentos"
    title="Editar serie: {{ $documentSeries->name }}"
>
    <x-slot:actions>
        <div class="text-body-secondary small">
            Próximo número: <strong>{{ $documentSeries->next_number }}</strong>
            <span class="ms-2 text-body-tertiary">(no editable — se asigna automáticamente)</span>
        </div>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4" style="max-width:680px">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            <form action="{{ route('finances.document-series.update', $documentSeries) }}" method="POST">
                @csrf @method('PUT')
                @include('finances.document-series._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <a href="{{ route('finances.document-series.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
