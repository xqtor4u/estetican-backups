@php($screenDebugId = 'FinCsShw')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas › Sesiones"
    title="{{ $cashSession->cashRegister?->name }} — {{ $cashSession->opened_at->format('d/m/Y') }}"
    subtitle="{{ $cashSession->isOpen() ? 'Sesión abierta · en curso' : 'Sesión cerrada' }}"
>
    <x-slot:actions>
        @if($cashSession->isOpen())
            <a href="{{ route('finances.cash-sessions.close', $cashSession) }}" class="btn btn-warning">
                Hacer corte
            </a>
        @endif
        <a href="{{ route('finances.cash-sessions.index') }}" class="btn btn-outline-secondary">Historial</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('success') }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
            {{ session('info') }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Resumen efectivo --}}
    <div class="row g-3 mb-3">
        <div class="col-12"><p class="text-body-secondary small fw-semibold mb-0 text-uppercase" style="letter-spacing:.05em">Efectivo (caja)</p></div>
        <div class="col-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <p class="text-body-secondary small mb-1">Fondo inicial</p>
                    <p class="fs-5 fw-bold mb-0">${{ number_format($cashSession->opening_amount, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <p class="text-body-secondary small mb-1">Cobros efectivo</p>
                    <p class="fs-5 fw-bold text-success mb-0">+${{ number_format($totalEfectivo, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <p class="text-body-secondary small mb-1">Entradas</p>
                    <p class="fs-5 fw-bold text-success mb-0">+${{ number_format($totalEntradas, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <p class="text-body-secondary small mb-1">Salidas</p>
                    <p class="fs-5 fw-bold text-danger mb-0">-${{ number_format($totalSalidas, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="card border-primary border-2 h-100">
                <div class="card-body text-center">
                    <p class="text-body-secondary small mb-1">Esperado en caja</p>
                    <p class="fs-5 fw-bold text-primary mb-0">${{ number_format($expectedAmount, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Resumen banco --}}
    <div class="row g-3 mb-4">
        <div class="col-12"><p class="text-body-secondary small fw-semibold mb-0 text-uppercase" style="letter-spacing:.05em">Banco / Tarjeta</p></div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <p class="text-body-secondary small mb-1">Total banco</p>
                    <p class="fs-5 fw-bold text-primary mb-0">${{ number_format($totalBanco, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    @if($cashSession->isOpen())
                        <p class="text-body-secondary small mb-1">Estado</p>
                        <span class="badge rounded-pill text-bg-success px-3">Abierta</span>
                    @else
                        <p class="text-body-secondary small mb-1">Diferencia caja</p>
                        <p class="fs-5 fw-bold mb-0 {{ $cashSession->difference >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $cashSession->difference >= 0 ? '+' : '' }}${{ number_format($cashSession->difference, 2) }}
                        </p>
                        <p class="text-body-secondary small mb-0">{{ $cashSession->difference >= 0 ? 'sobrante' : 'faltante' }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Info de apertura/cierre --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-body-secondary">Apertura</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-4">Por</dt>
                        <dd class="col-8">{{ $cashSession->openedBy?->name }}</dd>
                        <dt class="col-4">Fecha/hora</dt>
                        <dd class="col-8">{{ $cashSession->opened_at->format('d/m/Y H:i:s') }}</dd>
                        <dt class="col-4">Caja</dt>
                        <dd class="col-8">{{ $cashSession->cashRegister?->name }}</dd>
                        <dt class="col-4">Sucursal</dt>
                        <dd class="col-8">{{ $cashSession->branch?->name }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        @if(! $cashSession->isOpen())
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title text-body-secondary">Corte</h6>
                        <dl class="row mb-0 small">
                            <dt class="col-5">Por</dt>
                            <dd class="col-7">{{ $cashSession->closedBy?->name }}</dd>
                            <dt class="col-5">Fecha/hora</dt>
                            <dd class="col-7">{{ $cashSession->closed_at->format('d/m/Y H:i:s') }}</dd>
                            <dt class="col-5">Conteo físico</dt>
                            <dd class="col-7">${{ number_format($cashSession->closing_amount, 2) }}</dd>
                            <dt class="col-5">Esperado</dt>
                            <dd class="col-7">${{ number_format($cashSession->expected_amount, 2) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($cashSession->notes)
        <div class="alert alert-light border mb-4">
            {{ $cashSession->notes }}
        </div>
    @endif

    {{-- Movimientos manuales --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <span class="fw-semibold">Movimientos de caja</span>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-secondary">{{ $movements->count() }}</span>
                @if($cashSession->isOpen())
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalMovimiento">
                        + Registrar movimiento
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Concepto</th>
                        <th>Cuenta</th>
                        <th class="text-end">Monto</th>
                        @if($cashSession->isOpen())
                            <th style="width:48px"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $mov)
                        @php($movLabels = ['retiro'=>'Retiro','deposito_banco'=>'Dep. banco','gasto'=>'Gasto','perdida'=>'Pérdida','entrada'=>'Entrada'])
                        <tr>
                            <td class="small text-body-secondary">{{ $mov->created_at->format('d/m H:i') }}</td>
                            <td>
                                <span class="badge {{ $mov->direction === 'salida' ? 'text-bg-danger-subtle border border-danger-subtle text-danger-emphasis' : 'text-bg-success-subtle border border-success-subtle text-success-emphasis' }}">
                                    {{ $movLabels[$mov->type] ?? $mov->type }}
                                </span>
                            </td>
                            <td>
                                {{ $mov->concept }}
                                @if($mov->notes)
                                    <div class="text-body-secondary small">{{ $mov->notes }}</div>
                                @endif
                            </td>
                            <td class="small text-body-secondary">{{ $mov->counterpartAccount?->name ?? '—' }}</td>
                            <td class="text-end fw-semibold {{ $mov->direction === 'salida' ? 'text-danger' : 'text-success' }}">
                                {{ $mov->direction === 'salida' ? '-' : '+' }}${{ number_format($mov->amount, 2) }}
                            </td>
                            @if($cashSession->isOpen())
                                <td class="text-center">
                                    <form action="{{ route('finances.cash-sessions.movements.destroy', [$cashSession, $mov]) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1"
                                            data-confirm="¿Eliminar este movimiento y su póliza?">×</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $cashSession->isOpen() ? 6 : 5 }}" class="text-center py-3 text-body-secondary">
                                Sin movimientos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bloque efectivo --}}
    @php($cobrosEfectivo = $payments->where('destination', 'caja')->values())
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-transparent fw-semibold d-flex align-items-center justify-content-between">
            <span>Efectivo</span>
            <span class="badge text-bg-success">{{ $cobrosEfectivo->count() }}</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha/hora</th>
                        <th>Cliente</th>
                        <th>Método</th>
                        <th class="text-end">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cobrosEfectivo as $pago)
                        <tr>
                            <td class="small text-body-secondary">{{ \Carbon\Carbon::parse($pago->created_at)->format('d/m H:i') }}</td>
                            <td>{{ $pago->client_name ?? '—' }}</td>
                            <td class="small">{{ $pago->payment_method ?? '—' }}</td>
                            <td class="text-end fw-semibold">${{ number_format($pago->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-3 text-body-secondary">Sin cobros en efectivo.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($cobrosEfectivo->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-semibold">Total efectivo</td>
                            <td class="text-end fw-bold text-success">${{ number_format($totalEfectivo, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Bloque banco / tarjeta --}}
    @php($cobrosBanco = $payments->where('destination', 'banco')->values())
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent fw-semibold d-flex align-items-center justify-content-between">
            <span>Banco / Tarjeta</span>
            <span class="badge text-bg-primary">{{ $cobrosBanco->count() }}</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha/hora</th>
                        <th>Cliente</th>
                        <th>Método</th>
                        <th>Estado</th>
                        <th class="text-end">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cobrosBanco as $pago)
                        <tr>
                            <td class="small text-body-secondary">{{ \Carbon\Carbon::parse($pago->created_at)->format('d/m H:i') }}</td>
                            <td>{{ $pago->client_name ?? '—' }}</td>
                            <td class="small">{{ $pago->payment_method ?? '—' }}</td>
                            <td>
                                @if(isset($pago->cleared_at) && $pago->cleared_at)
                                    <span class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis">Acreditado</span>
                                @else
                                    <span class="badge text-bg-warning-subtle border border-warning-subtle text-warning-emphasis">En proceso</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">${{ number_format($pago->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-3 text-body-secondary">Sin cobros bancarios.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($cobrosBanco->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end fw-semibold">Total banco / tarjeta</td>
                            <td class="text-end fw-bold text-primary">${{ number_format($totalBanco, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>

@if($cashSession->isOpen())
<div class="modal fade" id="modalMovimiento" tabindex="-1" aria-labelledby="modalMovimientoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="modalMovimientoLabel">Registrar movimiento de caja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('finances.cash-sessions.movements.store', $cashSession) }}" method="POST">
                @csrf
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de movimiento <span class="text-danger">*</span></label>
                        <select name="type" id="movType" class="form-select" required>
                            <option value="">Selecciona…</option>
                            <option value="retiro">Retiro / disposición de efectivo</option>
                            <option value="deposito_banco">Depósito a banco</option>
                            <option value="gasto">Gasto de caja chica</option>
                            <option value="perdida">Pérdida / faltante</option>
                            <option value="entrada">Entrada de efectivo adicional</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Monto <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Concepto <span class="text-danger">*</span></label>
                        <input type="text" name="concept" class="form-control" maxlength="255" required
                               placeholder="Descripción breve del movimiento">
                    </div>

                    <div class="mb-3" id="wrapCounterpart">
                        <label class="form-label fw-semibold">Cuenta contable <span class="text-danger">*</span></label>
                        <select name="counterpart_account_id" id="movAccount" class="form-select" required>
                            <option value="">— Selecciona tipo primero —</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Notas (opcional)</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="1000"></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar movimiento</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ csp_nonce() }}">
(function () {
    var accounts = {
        retiro:         @json($gatoAccounts),
        deposito_banco: @json($bankAccounts),
        gasto:          @json($gatoAccounts),
        perdida:        { 24: 'Gastos generales' },
        entrada:        @json($capAccounts),
    };

    document.getElementById('movType').addEventListener('change', function () {
        var sel  = document.getElementById('movAccount');
        var opts = accounts[this.value] || {};
        sel.innerHTML = '<option value="">Selecciona cuenta…</option>';
        Object.entries(opts).forEach(function([id, name]) {
            var o = document.createElement('option');
            o.value = id; o.textContent = name;
            sel.appendChild(o);
        });
        // Auto-seleccionar si solo hay una opción
        if (Object.keys(opts).length === 1) sel.selectedIndex = 1;
    });
})();
</script>
@endpush
@endif

@endsection
