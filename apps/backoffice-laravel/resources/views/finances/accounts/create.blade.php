@php($screenDebugId = 'FinAccCre')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas / Catálogo de cuentas"
    title="Nueva cuenta"
    subtitle="Agrega una cuenta al catálogo contable."
/>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4" style="max-width:680px">
            <form action="{{ route('finances.accounts.store') }}" method="POST">
                @csrf
                @include('finances.accounts._form', ['account' => null])

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Crear cuenta</button>
                    <a href="{{ route('finances.accounts.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
