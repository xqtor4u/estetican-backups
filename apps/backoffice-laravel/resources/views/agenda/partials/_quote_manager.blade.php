<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Gestión de Presupuestos</h2>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateQuote">
                <i class="bi bi-plus-circle me-1"></i> Nueva Opción
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        @if($booking->quotes->isEmpty())
            <div class="text-center py-5">
                <div class="display-6 text-body-secondary opacity-25 mb-3"><i class="bi bi-calculator"></i></div>
                <p class="text-body-secondary mb-0">No hay presupuestos generados todavía.</p>
                <small class="text-body-secondary">Crea una propuesta detallada para el cliente.</small>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Versión</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($booking->quotes as $quote)
                            <tr class="{{ $quote->status === 'accepted' ? 'table-success-subtle' : '' }}">
                                <td>
                                    <div class="fw-semibold">{{ $quote->version_label }}</div>
                                    <small class="text-body-secondary">{{ $quote->created_at->format('d/m ' . $timeFormat) }}</small>
                                </td>
                                <td>
                                    <div class="small">
                                        @foreach($quote->items as $item)
                                            <span class="badge bg-light text-dark border me-1">{{ $item->name() }}@if((float) $item->quantity !== 1.0) × {{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}@endif</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td><span class="fw-bold">${{ number_format($quote->total_amount, 2) }}</span></td>
                                <td>
                                    @php($color = match($quote->status) { 'draft' => 'secondary', 'accepted' => 'success', 'rejected' => 'danger', default => 'secondary' })
                                    <span class="badge text-bg-{{ $color }}">{{ ucfirst($quote->status) }}</span>
                                </td>
                                <td class="text-end">
                                    @if($quote->status === 'draft')
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAcceptQuote{{ $quote->id }}">
                                            Aceptar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<!-- Modal: Create Quote -->
<div class="modal fade" id="modalCreateQuote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('agenda.quotes.store', $booking) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Generar Nuevo Presupuesto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" x-data="{
                items: [
                    @foreach($booking->services as $bs)
                    { service_id: '{{ $bs->service_id }}', item_id: '', group_id: '', name: '{{ $bs->service->name }}', price: '{{ $bs->current_price }}', quantity: '{{ rtrim(rtrim(number_format($bs->quantity, 2), '0'), '.') ?: '1' }}' },
                    @endforeach
                    @foreach($booking->items as $bi)
                    { service_id: '', item_id: '{{ $bi->item_id }}', group_id: '', name: '{{ $bi->item->name }}', price: '{{ $bi->current_price }}', quantity: '{{ rtrim(rtrim(number_format($bi->quantity, 2), '0'), '.') ?: '1' }}' },
                    @endforeach
                ],
                groups: @json($groups->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'components' => $g->components->map(fn ($c) => [
                        'service_id' => $c->service_id,
                        'item_id' => $c->item_id,
                        'name' => $c->name(),
                        'price' => $c->unitPrice(),
                        'quantity' => (float) $c->quantity,
                    ]),
                ])),
                addService() {
                    let sel = $refs.serviceSelector;
                    let opt = sel.options[sel.selectedIndex];
                    if (!opt.value) return;
                    this.items.push({ service_id: opt.value, item_id: '', group_id: '', name: opt.getAttribute('data-name'), price: opt.getAttribute('data-price'), quantity: 1 });
                    sel.selectedIndex = 0;
                },
                addItem() {
                    let sel = $refs.itemSelector;
                    let opt = sel.options[sel.selectedIndex];
                    if (!opt.value) return;
                    this.items.push({ service_id: '', item_id: opt.value, group_id: '', name: opt.getAttribute('data-name'), price: opt.getAttribute('data-price'), quantity: 1 });
                    sel.selectedIndex = 0;
                },
                addGroup() {
                    let sel = $refs.groupSelector;
                    if (!sel.value) return;
                    let group = this.groups.find(g => g.id == sel.value);
                    if (!group) return;
                    group.components.forEach(c => {
                        this.items.push({ service_id: c.service_id ?? '', item_id: c.item_id ?? '', group_id: group.id, name: c.name, price: c.price, quantity: c.quantity });
                    });
                    sel.selectedIndex = 0;
                },
                removeItem(index) {
                    this.items.splice(index, 1);
                }
            }">
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Etiqueta de versión</label>
                        <input type="text" name="version_label" class="form-control" placeholder="Ej: Opción Integral, Opción Básica..." required>
                    </div>
                </div>
                
                <h6 class="mb-3 d-flex justify-content-between align-items-center">
                    <span>Servicios de la Propuesta</span>
                    <span class="badge bg-primary" x-text="items.length + ' items'"></span>
                </h6>

                <div class="row g-2 mb-3" id="quote-items-container">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="col-12 mb-2">
                            <div class="card bg-light border-0">
                                <div class="card-body py-2">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <input type="hidden" :name="'items['+index+'][service_id]'" :value="item.service_id">
                                            <input type="hidden" :name="'items['+index+'][item_id]'" :value="item.item_id">
                                            <input type="hidden" :name="'items['+index+'][group_id]'" :value="item.group_id">
                                            <div class="fw-semibold" x-text="item.name"></div>
                                            <span class="badge bg-white text-dark border" x-show="item.item_id" x-cloak>Artículo</span>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" :name="'items['+index+'][quantity]'" x-model="item.quantity" class="form-control form-control-sm" step="0.01" min="0.01" title="Cantidad">
                                        </div>
                                        <div class="col-md-4">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">$</span>
                                                <input type="number" :name="'items['+index+'][price]'" x-model="item.price" class="form-control" step="0.01">
                                                <button type="button" @click="removeItem(index)" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="p-3 border rounded-3 bg-white mb-3">
                    <label class="form-label small fw-bold text-uppercase text-body-secondary">Agregar grupo completo</label>
                    <div class="input-group mb-3">
                        <select x-ref="groupSelector" class="form-select">
                            <option value="">Seleccionar grupo...</option>
                            @foreach($groups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }} (${{ number_format($g->calculatedPrice(), 2) }})</option>
                            @endforeach
                        </select>
                        <button type="button" @click="addGroup()" class="btn btn-outline-primary">
                            <i class="bi bi-collection"></i> Agregar grupo
                        </button>
                    </div>

                    <label class="form-label small fw-bold text-uppercase text-body-secondary">Agregar servicio adicional</label>
                    <div class="input-group mb-3">
                        <select x-ref="serviceSelector" class="form-select">
                            <option value="">Seleccionar servicio...</option>
                            @foreach($services as $s)
                                <option value="{{ $s->id }}" data-name="{{ $s->name }}" data-price="{{ $s->suggested_price ?? $s->price ?? 0 }}">{{ $s->name }} (${{ number_format($s->price ?? 0, 2) }})</option>
                            @endforeach
                        </select>
                        <button type="button" @click="addService()" class="btn btn-outline-primary">
                            <i class="bi bi-plus-lg"></i> Agregar
                        </button>
                    </div>

                    <label class="form-label small fw-bold text-uppercase text-body-secondary">Agregar artículo suelto</label>
                    <div class="input-group">
                        <select x-ref="itemSelector" class="form-select">
                            <option value="">Seleccionar artículo...</option>
                            @foreach($items as $it)
                                <option value="{{ $it->id }}" data-name="{{ $it->name }}" data-price="{{ $it->price ?? 0 }}">{{ $it->name }} (${{ number_format($it->price ?? 0, 2) }})</option>
                            @endforeach
                        </select>
                        <button type="button" @click="addItem()" class="btn btn-outline-primary">
                            <i class="bi bi-plus-lg"></i> Agregar
                        </button>
                    </div>
                </div>
                
                <div>
                    <label class="form-label small fw-bold text-uppercase text-body-secondary">Notas del presupuesto</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Información aclaratoria para el cliente..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Presupuesto</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Accept Quote -->
@foreach($booking->quotes as $quote)
    @if($quote->status === 'draft')
        <div class="modal fade" id="modalAcceptQuote{{ $quote->id }}" tabindex="-1" aria-hidden="true" x-data="{
            total: {{ $quote->total_amount }},
            suggested: 0,
            init() {
                let s = 0;
                @foreach($quote->items as $item)
                    @if($item->service && $item->service->requires_advance)
                        s += {{ $item->lineTotal() }} * ({{ $item->service->advance_percentage ?? app(\App\Support\SystemSettings\SystemSettings::class)->all()['service_advance_percentage'] }} / 100);
                    @endif
                @endforeach
                this.suggested = s.toFixed(2);
                $refs.advanceInput{{ $quote->id }}.value = this.suggested;
            }
        }">
            <div class="modal-dialog">
                <form action="{{ route('agenda.quotes.accept', [$booking, $quote]) }}" method="POST" class="modal-content shadow-lg border-0">
                    @csrf
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Aceptar Presupuesto</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-4">
                            <div class="display-6 fw-bold text-success">${{ number_format($quote->total_amount, 2) }}</div>
                            <div class="text-body-secondary">{{ $quote->version_label }}</div>
                        </div>
                        
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <p class="small text-body-secondary mb-3">Se generará una <strong>Orden de Trabajo</strong> y se cerrarán las otras opciones.</p>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold d-flex justify-content-between">
                                    Registro de Anticipo
                                    <span class="badge bg-info-subtle text-info border" x-show="suggested > 0" x-text="'Sugerido: $' + suggested"></span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="advance_amount" x-ref="advanceInput{{ $quote->id }}" class="form-control form-control-lg" step="0.01" min="0">
                                </div>
                                <div class="form-text">Monto pagado por el cliente para confirmar.</div>
                            </div>
                            
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label class="form-label fw-bold">Método de pago</label>
                                    <select name="advance_payment_method" class="form-select" x-model="method" x-init="method = 'Efectivo'">
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Tarjeta">Tarjeta (Deb/Cred)</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-bold">Destino</label>
                                    <select name="destination" class="form-select" :class="method === 'Tarjeta' || method === 'Transferencia' ? 'bg-info-subtle' : ''">
                                        <option value="caja" :selected="method === 'Efectivo'">En Caja</option>
                                        <option value="banco" :selected="method === 'Tarjeta' || method === 'Transferencia'">En Banco</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success px-4" @if(!app(\App\Support\SystemSettings\SystemSettings::class)->all()['allow_override_advance_requirement']) :disabled="$refs.advanceInput{{ $quote->id }}.value < suggested" @endif>
                            Confirmar y Abrir Orden
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
