@php($screenDebugId = 'FinCsOpn')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas › Cajas"
    title="Abrir sesión — {{ $cashRegister->name }}"
    subtitle="Declara el fondo inicial con el que arranca la caja hoy."
>
    <x-slot:actions>
        <a href="{{ route('finances.cash-registers.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content">
    <form action="{{ route('finances.cash-sessions.store', $cashRegister) }}" method="POST">
        @csrf

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent fw-semibold">Datos de apertura</div>
            <div class="card-body">

                <div class="mb-4">
                    <div class="row g-3 text-body-secondary small mb-2">
                        <div class="col-auto">
                            <strong>Caja:</strong> {{ $cashRegister->name }}
                        </div>
                        <div class="col-auto">
                            <strong>Sucursal:</strong> {{ $cashRegister->branch?->name ?? '—' }}
                        </div>
                        <div class="col-auto">
                            <strong>Hora de apertura:</strong> {{ now()->format('d/m/Y H:i') }}
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Fondo inicial <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="opening_amount" class="form-control @error('opening_amount') is-invalid @enderror"
                                   value="{{ old('opening_amount', '0.00') }}" step="0.01" min="0" required autofocus>
                            @error('opening_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text">Efectivo físico contado antes de iniciar operaciones.</div>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Notas (opcional)</label>
                        <input type="text" name="notes" class="form-control"
                               value="{{ old('notes') }}" placeholder="Observaciones de apertura…" maxlength="500">
                    </div>
                </div>

            </div>
            <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
                <a href="{{ route('finances.cash-registers.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-success">
                    Abrir caja
                </button>
            </div>
        </div>

    </form>
</div>
@endsection
