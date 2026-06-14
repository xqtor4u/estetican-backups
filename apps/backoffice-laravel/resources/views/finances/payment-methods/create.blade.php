@php($screenDebugId = 'FinPmCre')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas / Métodos de pago"
    title="Nuevo método de pago"
/>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4" style="max-width:680px">
            <form action="{{ route('finances.payment-methods.store') }}" method="POST">
                @csrf
                @include('finances.payment-methods._form', ['paymentMethod' => null])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Crear método</button>
                    <a href="{{ route('finances.payment-methods.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
