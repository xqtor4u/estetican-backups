@php($screenDebugId = 'FinCsClose')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas › Cajas"
    title="Corte de caja — {{ $cashSession->cashRegister?->name }}"
    subtitle="Cuenta el efectivo físico y registra el cierre de la sesión."
>
    <x-slot:actions>
        <a href="{{ route('finances.cash-sessions.show', $cashSession) }}" class="btn btn-outline-secondary">Cancelar</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content">

    {{-- Resumen del sistema --}}
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center">
                    <p class="text-body-secondary small mb-1">Fondo inicial</p>
                    <p class="fs-5 fw-bold mb-0">${{ number_format($cashSession->opening_amount, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center">
                    <p class="text-body-secondary small mb-1">Cobros efectivo</p>
                    <p class="fs-5 fw-bold text-success mb-0">+${{ number_format($totalCobros, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card border-primary border-2 h-100">
                <div class="card-body text-center">
                    <p class="text-body-secondary small mb-1">Esperado en caja</p>
                    <p class="fs-5 fw-bold text-primary mb-0">${{ number_format($expectedAmount, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('finances.cash-sessions.do-close', $cashSession) }}" method="POST">
        @csrf

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent fw-semibold">Conteo físico al cierre</div>
            <div class="card-body">

                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Efectivo contado <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input type="number" name="closing_amount" id="closing_amount"
                                   class="form-control @error('closing_amount') is-invalid @enderror"
                                   value="{{ old('closing_amount') }}" step="0.01" min="0" required autofocus>
                            @error('closing_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text">Cuenta el dinero físico en la caja ahora.</div>
                    </div>

                    <div class="col-md-7 d-flex align-items-center">
                        <div id="diff-preview" class="p-3 rounded bg-light w-100 text-center" style="display:none!important">
                            <p class="text-body-secondary small mb-1">Diferencia estimada</p>
                            <p id="diff-value" class="fs-4 fw-bold mb-0">—</p>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Notas del corte (opcional)</label>
                        <input type="text" name="notes" class="form-control"
                               value="{{ old('notes', $cashSession->notes) }}"
                               placeholder="Observaciones, aclaraciones…" maxlength="500">
                    </div>
                </div>

            </div>
            <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
                <a href="{{ route('finances.cash-sessions.show', $cashSession) }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-warning">
                    Confirmar corte
                </button>
            </div>
        </div>

    </form>
</div>

@push('scripts')
<script nonce="{{ csp_nonce() }}">
(function () {
    const expected = {{ $expectedAmount }};
    const input    = document.getElementById('closing_amount');
    const preview  = document.getElementById('diff-preview');
    const diffEl   = document.getElementById('diff-value');

    input.addEventListener('input', function () {
        const val = parseFloat(this.value);
        if (isNaN(val)) { preview.style.display = 'none'; return; }
        const diff = val - expected;
        preview.style.removeProperty('display');
        diffEl.textContent = (diff >= 0 ? '+' : '') + '$' + Math.abs(diff).toFixed(2) + ' ' + (diff >= 0 ? 'sobrante' : 'faltante');
        diffEl.className   = 'fs-4 fw-bold mb-0 ' + (diff >= 0 ? 'text-success' : 'text-danger');
    });
})();
</script>
@endpush
@endsection
