@php($screenDebugId = 'FinAccEdt')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas / Catálogo de cuentas"
    title="Editar cuenta {{ $account->code }}"
    subtitle="{{ $account->name }}"
/>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4" style="max-width:680px">
            <form action="{{ route('finances.accounts.update', $account) }}" method="POST">
                @csrf @method('PUT')
                @include('finances.accounts._form', ['account' => $account])

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <a href="{{ route('finances.accounts.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
