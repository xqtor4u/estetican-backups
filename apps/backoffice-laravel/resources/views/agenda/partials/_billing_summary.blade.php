<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Estado de Cuenta y Liquidación</h2>
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
            $balance     = (float) ($acceptedQuote?->total_amount ?? $booking->total_estimated_price ?? 0) - $totalPaid;
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
                            @endif
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="fw-bold">
                                <td>Subtotal Liquidación</td>
                                <td class="text-end">${{ number_format($acceptedQuote?->total_amount ?? 0, 2) }}</td>
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
                        <button type="button" class="btn btn-primary w-100 mb-2 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalRegisterFinalPayment">
                            <i class="bi bi-cash-stack me-1"></i> LIQUIDAR SALDO
                        </button>
                    @else
                        <div class="alert alert-success d-flex align-items-center justify-content-center py-2 mb-2 border-0 shadow-sm">
                            <i class="bi bi-check-all me-2 h4 mb-0"></i> CUENTA PAGADA
                        </div>
                    @endif

                    <a href="{{ route('reports.invoice', $booking) }}" target="_blank" class="btn btn-outline-light w-100 py-2">
                        <i class="bi bi-printer me-1"></i> IMPRIMIR RECIBO
                    </a>
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

<!-- Modal: Final Payment (Liquidation) -->
@if($acceptedQuote)
<div class="modal fade" id="modalRegisterFinalPayment" tabindex="-1" aria-hidden="true" x-data="{
    balance: {{ $balance }},
    methods: @json($paymentMethods->map(fn ($pm) => ['code' => $pm->code, 'name' => $pm->name, 'type' => $pm->type])),
    methodCode: '',
    get isCash() {
        let m = this.methods.find(m => m.code === this.methodCode);
        return m ? m.type === 'cash' : true;
    }
}">
    <div class="modal-dialog">
        <form action="{{ route('agenda.quotes.register-payment', [$booking, $acceptedQuote]) }}" method="POST" class="modal-content border-0 shadow-lg">
            @csrf
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Liquidar Saldo Pendiente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="text-uppercase small text-body-secondary fw-bold mb-1">Monto a cobrar</div>
                    <div class="display-5 fw-bold text-dark">${{ number_format($balance, 2) }}</div>
                </div>

                <input type="hidden" name="amount" :value="balance">
                <input type="hidden" name="category" value="liquidation">

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Método de pago</label>
                        <select name="payment_method_code" class="form-select form-select-lg" x-model="methodCode" required>
                            <option value="">Selecciona...</option>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->code }}">{{ $pm->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" x-show="methodCode" x-cloak>
                            Destino: <span :class="isCash ? 'text-warning fw-bold' : 'text-info fw-bold'" x-text="isCash ? 'En Caja' : 'En Banco'"></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-body-secondary text-uppercase">Notas de la transacción</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Referencia de tarjeta, folio, etc."></textarea>
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-0 small border-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Los pagos con tarjeta se marcan automáticamente como <strong>"En Banco"</strong> para el reporte fiscal.
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark px-4 py-2 fw-bold">
                    <i class="bi bi-check-lg me-1"></i> REGISTRAR COBRO Y CERRAR
                </button>
            </div>
        </form>
    </div>
</div>
@endif
