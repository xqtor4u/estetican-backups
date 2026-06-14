@php($screenDebugId = 'FinPmEdt')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas / Métodos de pago"
    title="Editar: {{ $paymentMethod->name }}"
/>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4" style="max-width:680px">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            <form action="{{ route('finances.payment-methods.update', $paymentMethod) }}" method="POST">
                @csrf @method('PUT')
                @include('finances.payment-methods._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <a href="{{ route('finances.payment-methods.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
