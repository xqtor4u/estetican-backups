<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h5 mb-0">Estado de Cuenta y Liquidación</h2>
                <div class="small text-body-secondary">
                    {{ $booking->pet?->name ?? 'Mascota' }}
                    @if($booking->order_folio) · <span class="font-monospace">{{ $booking->order_folio }}</span> @endif
                    · {{ $booking->services->map(fn ($l) => $l->service?->name)->filter()->implode(', ') ?: 'Sin servicios' }}
                </div>
            </div>
            <span class="badge rounded-pill text-bg-success">Servicio Finalizado</span>
        </div>
    </div>
    <div class="card-body">
        @php
            $acceptedQuote = $booking->quotes->firstWhere('status', 'accepted');

            // Pagos legacy (ligados al Quote vía CashLedger/BankLedger)
            $cashPayments  = $acceptedQuote
                ? $booking->pet->client->cashLedgers()
                    ->where('payable_id', $acceptedQuote->id)
                    ->where('payable_type', \App\Models\Quote::class)
                    ->get()
                : collect();
            $bankPayments  = $acceptedQuote
                ? $booking->pet->client->bankLedgers()
                    ->where('payable_id', $acceptedQuote->id)
                    ->where('payable_type', \App\Models\Quote::class)
                    ->get()
                : collect();

            // Pagos nuevos (ligados directamente al SpaBooking vía Payment model — cobros desde app móvil)
            $newPayments = \App\Models\Payment::where('payable_type', \App\Models\SpaBooking::class)
                ->where('payable_id', $booking->id)
                ->get();

            $allPayments = $cashPayments->concat($bankPayments)->concat($newPayments)->sortBy('created_at');
            $totalPaid   = (float) $allPayments->sum('amount');

            // Sin presupuesto aceptado (cita cerrada por "Iniciar cita" + "Terminar y cobrar",
            // SYNC-052/053), el resumen de cargos y el subtotal salen de las propias líneas de
            // servicio de la cita — las que no se cancelaron ni se marcaron "no realizada".
            $billableLines = $booking->services->filter(
                fn ($line) => $line->cancelled_at === null && $line->not_performed_at === null
            );
            $subtotal    = (float) ($acceptedQuote?->total_amount ?? $billableLines->sum('current_price'));
            $balance     = $subtotal - $totalPaid;
            $paymentAction = $acceptedQuote
                ? route('agenda.quotes.register-payment', [$booking, $acceptedQuote])
                : route('agenda.payments.store', $booking);

            // El recibo solo existe (y solo tiene sentido imprimirlo) cuando ya se registró
            // un cobro con su documento contable.
            $hasReceipt = \App\Models\Document::where('documentable_type', \App\Models\SpaBooking::class)
                ->where('documentable_id', $booking->id)
                ->where('status', 'emitido')
                ->exists();
        @endphp

        <div class="row g-4">
            <div class="col-md-8">
                <h6 class="text-uppercase small text-body-secondary fw-bold mb-3">Resumen de Cargos</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Concepto</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($acceptedQuote)
                                @foreach($acceptedQuote->items as $item)
                                    <tr>
                                        <td>
                                            {{ $item->name() }}
                                            @if((float) $item->quantity !== 1.0)
                                                <span class="text-body-secondary small">× {{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">${{ number_format($item->lineTotal(), 2) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                @forelse($billableLines as $line)
                                    <tr>
                                        <td>{{ $line->service?->name ?? 'Servicio' }}</td>
                                        <td class="text-end">${{ number_format((float) $line->current_price, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-body-secondary text-center small">Sin cargos por cobrar (todos los servicios se cancelaron o no se realizaron).</td>
                                    </tr>
                                @endforelse
                            @endif
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="fw-bold">
                                <td>Subtotal Liquidación</td>
                                <td class="text-end">${{ number_format($subtotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <h6 class="text-uppercase small text-body-secondary fw-bold mb-3 mt-4">Pagos y Abonos</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless">
                        <tbody>
                            @forelse($allPayments as $payment)
                                <tr>
                                    <td>
                                        <i class="bi bi-chevron-right small opacity-50 me-1"></i>
                                        {{ ucfirst($payment->category) }} ({{ $payment->payment_method }})
                                        @if($payment->notes)
                                            <small class="text-body-secondary d-block">{{ $payment->notes }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end text-success fw-semibold">-${{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-body-secondary text-center small">Sin abonos registrados</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-4">
                <div class="p-4 bg-dark text-white rounded-4 shadow-lg text-center h-100 d-flex flex-column justify-content-center">
                    <div class="text-uppercase small opacity-75 mb-1">Saldo Pendiente</div>
                    <div class="h1 fw-bold mb-3">${{ number_format(max(0, $balance), 2) }}</div>

                    @if($balance > 0)
                        <button type="button" id="btnLiquidarSaldo" class="btn btn-primary w-100 mb-2 py-2 fw-bold shadow-sm">
                            <i class="bi bi-cash-stack me-1"></i> LIQUIDAR SALDO
                        </button>
                    @else
                        <div class="alert alert-success d-flex align-items-center justify-content-center py-2 mb-2 border-0 shadow-sm">
                            <i class="bi bi-check-all me-2 h4 mb-0"></i> CUENTA PAGADA
                        </div>
                    @endif

                    @if($hasReceipt)
                        <a href="{{ route('reports.invoice', $booking) }}" target="_blank" class="btn btn-outline-light w-100 py-2">
                            <i class="bi bi-printer me-1"></i> IMPRIMIR RECIBO
                        </a>
                    @else
                        <div class="small opacity-75">El recibo se genera al registrar el cobro.</div>
                    @endif
                </div>
            </div>
        </div>

        @if(auth()->user()->can('asientos.aprobar') || auth()->user()->is_super_admin)
            @php
                $documents = \App\Models\Document::where('documentable_type', \App\Models\SpaBooking::class)
                    ->where('documentable_id', $booking->id)
                    ->with('cancelledBy', 'supersedes')
                    ->orderByDesc('created_at')
                    ->get();
            @endphp
            @if($documents->isNotEmpty())
                <hr class="my-4">
                <h6 class="text-uppercase small text-body-secondary fw-bold mb-3">Recibos generados</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Estado</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documents as $doc)
                                <tr>
                                    <td>
                                        <span class="fw-semibold">{{ $doc->folio_display }}</span>
                                        @if($doc->supersedes_document_id)
                                            <br><small class="text-body-secondary">reemplaza a {{ $doc->supersedes?->folio_display }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($doc->status === 'emitido')
                                            <span class="badge text-bg-success">Emitido</span>
                                        @else
                                            <span class="badge text-bg-secondary">Cancelado</span>
                                            <div class="small text-body-secondary mt-1">
                                                {{ $doc->cancellation_type === 'refund' ? 'Reembolso' : 'Corrección' }} —
                                                {{ $doc->cancellation_reason }}
                                                <br>por {{ $doc->cancelledBy?->name }}, {{ $doc->cancelled_at?->format('d/m/Y H:i') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">${{ number_format($doc->total, 2) }}</td>
                                    <td class="text-end">
                                        @if($doc->status === 'emitido')
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelDocument{{ $doc->id }}">
                                                Cancelar
                                            </button>
                                        @elseif($doc->cancellation_type === 'correction' && ! $doc->replacement()->exists())
                                            <form action="{{ route('finances.documents.reissue', $doc) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary" data-confirm="¿Reemitir un recibo nuevo a partir del estado actual de la cita?">
                                                    Reemitir
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>

<!-- Modales: Cancelar Documento -->
@foreach($documents ?? [] as $doc)
    @if($doc->status === 'emitido')
        <div class="modal fade" id="modalCancelDocument{{ $doc->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form action="{{ route('finances.documents.cancel', $doc) }}" method="POST" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Cancelar recibo {{ $doc->folio_display }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Motivo de la cancelación</label>
                            <select name="cancellation_type" class="form-select" required>
                                <option value="correction">Corrección de datos — el dinero se queda donde está</option>
                                <option value="refund">Reembolso real — se le devuelve el dinero al cliente</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Explicación</label>
                            <textarea name="cancellation_reason" class="form-control" rows="3" required placeholder="Ej. Se capturó mal el nombre del servicio / El cliente pidió que le devolviéramos su dinero"></textarea>
                        </div>
                        <div class="alert alert-warning small mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            El folio {{ $doc->folio_display }} queda cancelado permanentemente (nunca se borra ni se reutiliza). Si es corrección, después podrás reemitir un recibo nuevo desde este mismo listado.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-danger">Confirmar cancelación</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach

<!-- Modal: Final Payment (Liquidation) — 2 fases: cobrar → confirmar + imprimir recibo -->
{{-- JS plano, sin Alpine ni data-api de Bootstrap: en este deploy (Vite ESM) el data-api de modales no engancha de forma fiable. --}}
@if($balance > 0)
<div class="modal fade" id="modalRegisterFinalPayment" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="finalPaymentTitle">Liquidar saldo pendiente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" data-final-payment-close></button>
            </div>

            {{-- Fase 1: capturar el cobro --}}
            <div class="modal-body p-4" data-final-payment-phase="form">
                <div class="text-center mb-4">
                    <div class="text-uppercase small text-body-secondary fw-bold mb-1">Monto a cobrar</div>
                    <div class="display-5 fw-bold text-dark">${{ number_format($balance, 2) }}</div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold" for="finalPaymentMethod">Método de pago</label>
                        <select class="form-select form-select-lg" id="finalPaymentMethod" required>
                            <option value="">Selecciona...</option>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->code }}" data-type="{{ $pm->type }}">{{ $pm->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text d-none" id="finalPaymentDestination">
                            Destino: <span class="fw-bold" id="finalPaymentDestinationLabel"></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-body-secondary text-uppercase" for="finalPaymentNotes">Notas de la transacción</label>
                        <textarea class="form-control" id="finalPaymentNotes" rows="2" placeholder="Referencia de tarjeta, folio, etc."></textarea>
                    </div>
                </div>

                <div class="alert alert-danger mt-3 mb-0 small d-none" id="finalPaymentError"></div>

                <div class="alert alert-info mt-3 mb-0 small border-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Los pagos con tarjeta se marcan automáticamente como <strong>"En Banco"</strong> para el reporte fiscal.
                </div>
            </div>
            <div class="modal-footer bg-light border-0" data-final-payment-phase="form">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal" data-final-payment-close>Cancelar</button>
                <button type="button" class="btn btn-dark px-4 py-2 fw-bold" id="finalPaymentSubmit">
                    <i class="bi bi-check-lg me-1"></i> Registrar cobro
                </button>
            </div>

            {{-- Fase 2: confirmación + imprimir recibo (el botón solo existe porque el cobro fue exitoso) --}}
            <div class="modal-body p-4 text-center d-none" data-final-payment-phase="done">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <p class="fw-bold h5 mt-2 mb-1">Cobro registrado por ${{ number_format($balance, 2) }}</p>
                <p class="text-body-secondary small mb-4">El recibo ya está disponible.</p>
                <a href="#" target="_blank" class="btn btn-outline-dark w-100 py-2 fw-bold disabled" id="finalPaymentReceipt">
                    <i class="bi bi-printer me-1"></i> Imprimir recibo de pago
                </a>
            </div>
            <div class="modal-footer bg-light border-0 d-none" data-final-payment-phase="done">
                <button type="button" class="btn btn-dark px-4 py-2 fw-bold" id="finalPaymentFinish">
                    Terminar
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
(function () {
    var modalEl = document.getElementById('modalRegisterFinalPayment');
    if (!modalEl) return;

    var ACTION = @json($paymentAction);
    var CSRF = @json(csrf_token());
    var BALANCE = @json((float) $balance);
    var INVOICE_URL = @json(route('reports.invoice', $booking));

    var phase = 'form';
    var submitting = false;
    var instance = null;

    function phaseNodes(name) {
        return modalEl.querySelectorAll('[data-final-payment-phase="' + name + '"]');
    }
    function setPhase(name) {
        phase = name;
        phaseNodes('form').forEach(function (n) { n.classList.toggle('d-none', name !== 'form'); });
        phaseNodes('done').forEach(function (n) { n.classList.toggle('d-none', name !== 'done'); });
        var title = document.getElementById('finalPaymentTitle');
        if (title) title.textContent = name === 'done' ? 'Cobro registrado' : 'Liquidar saldo pendiente';
    }
    function showError(msg) {
        var box = document.getElementById('finalPaymentError');
        if (!box) return;
        box.textContent = msg || '';
        box.classList.toggle('d-none', !msg);
    }
    function openModal() {
        setPhase('form');
        showError('');
        submitting = false;
        var BS = window.bootstrap;
        if (BS && BS.Modal) {
            instance = instance || new BS.Modal(modalEl, { backdrop: 'static' });
            instance.show();
            return;
        }
        // Fallback manual si Bootstrap JS no cargó
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        if (!document.querySelector('.modal-backdrop[data-final-payment-backdrop]')) {
            var bd = document.createElement('div');
            bd.className = 'modal-backdrop fade show';
            bd.setAttribute('data-final-payment-backdrop', '');
            document.body.appendChild(bd);
        }
    }
    function closeModal() {
        var BS = window.bootstrap;
        if (BS && BS.Modal && instance) { instance.hide(); return; }
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        var bd = document.querySelector('.modal-backdrop[data-final-payment-backdrop]');
        if (bd) bd.remove();
    }

    var methodSel = document.getElementById('finalPaymentMethod');
    if (methodSel) {
        methodSel.addEventListener('change', function () {
            var opt = methodSel.options[methodSel.selectedIndex];
            var type = opt ? opt.getAttribute('data-type') : '';
            var hint = document.getElementById('finalPaymentDestination');
            var label = document.getElementById('finalPaymentDestinationLabel');
            if (!hint || !label) return;
            if (!methodSel.value) { hint.classList.add('d-none'); return; }
            var isCash = type ? type === 'cash' : true;
            label.textContent = isCash ? 'En Caja' : 'En Banco';
            label.className = 'fw-bold ' + (isCash ? 'text-warning' : 'text-info');
            hint.classList.remove('d-none');
        });
    }

    var submitBtn = document.getElementById('finalPaymentSubmit');
    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (submitting) return;
            var code = methodSel ? methodSel.value : '';
            if (!code) { showError('Elige un método de pago.'); return; }
            submitting = true;
            showError('');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Registrando…';

            var body = new FormData();
            body.append('amount', BALANCE);
            body.append('category', 'liquidation');
            body.append('payment_method_code', code);
            body.append('notes', document.getElementById('finalPaymentNotes') ? document.getElementById('finalPaymentNotes').value : '');

            fetch(ACTION, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: body,
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    return { ok: res.ok, data: data };
                });
            }).then(function (r) {
                if (!r.ok || r.data.ok === false) {
                    showError(r.data.message || 'No se pudo registrar el cobro. Revisa la configuración de recibos/cuentas.');
                    return;
                }
                var link = document.getElementById('finalPaymentReceipt');
                if (link) {
                    link.href = r.data.receipt_url || INVOICE_URL;
                    link.classList.remove('disabled');
                }
                setPhase('done');
            }).catch(function () {
                showError('Error de red al registrar el cobro. Vuelve a intentar.');
            }).finally(function () {
                submitting = false;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Registrar cobro';
            });
        });
    }

    var finishBtn = document.getElementById('finalPaymentFinish');
    if (finishBtn) finishBtn.addEventListener('click', function () { window.location.reload(); });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (phase === 'done') window.location.reload();
    });
    modalEl.querySelectorAll('[data-final-payment-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (phase === 'done') { window.location.reload(); return; }
            closeModal();
        });
    });

    var trigger = document.getElementById('btnLiquidarSaldo');
    if (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            openModal();
        });
    }

    {{-- Llega "cobrar=1" desde "Terminar y cobrar" de la agenda: abre directo el modal --}}
    @if(request()->boolean('cobrar'))
        if (document.readyState === 'complete') { openModal(); }
        else { window.addEventListener('load', openModal); }
    @endif
})();
</script>
@endif
