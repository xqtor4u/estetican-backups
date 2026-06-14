@php($screenDebugId = 'FinCrCre')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas / Cajas"
    title="Nueva caja"
/>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4" style="max-width:560px">
            <form action="{{ route('finances.cash-registers.store') }}" method="POST">
                @csrf
                @include('finances.cash-registers._form', ['cashRegister' => null])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Crear caja</button>
                    <a href="{{ route('finances.cash-registers.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
