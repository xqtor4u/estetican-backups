@php($screenDebugId = 'FinCrEdt')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas / Cajas"
    title="Editar: {{ $cashRegister->name }}"
/>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4" style="max-width:560px">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            <form action="{{ route('finances.cash-registers.update', $cashRegister) }}" method="POST">
                @csrf @method('PUT')
                @include('finances.cash-registers._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <a href="{{ route('finances.cash-registers.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
