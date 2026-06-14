@php($screenDebugId = 'FinDsCre')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas / Series de documentos"
    title="Nueva serie de documentos"
/>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4" style="max-width:680px">
            <form action="{{ route('finances.document-series.store') }}" method="POST">
                @csrf
                @include('finances.document-series._form', ['documentSeries' => null])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Crear serie</button>
                    <a href="{{ route('finances.document-series.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
