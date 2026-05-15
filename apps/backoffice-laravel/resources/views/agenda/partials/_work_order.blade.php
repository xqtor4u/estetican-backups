<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Orden de Trabajo #{{ $booking->id }}</h2>
            <span class="badge rounded-pill text-bg-warning">En Proceso</span>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-7">
                <!-- Status Bar: Cage & Time -->
                @php($allocation = $booking->resourceAllocations->first())
                <div class="d-flex gap-3 mb-4" x-data="{ 
                    startTime: new Date('{{ $booking->quotes->firstWhere('status', 'accepted')?->updated_at ?? $booking->updated_at }}').getTime(),
                    now: new Date().getTime(),
                    timer: '',
                    update() {
                        let diff = Math.floor((new Date().getTime() - this.startTime) / 1000);
                        if (diff < 0) diff = 0;
                        let h = Math.floor(diff / 3600);
                        let m = Math.floor((diff % 3600) / 60);
                        let s = diff % 60;
                        this.timer = [h, m, s].map(v => v < 10 ? '0' + v : v).join(':');
                    }
                }" x-init="update(); setInterval(() => update(), 1000)">
                    <div class="flex-grow-1 p-3 border rounded-3 bg-white shadow-sm d-flex align-items-center">
                        <div class="bg-primary-subtle text-primary rounded-circle p-2 me-3">
                            <i class="bi bi-door-open-fill h4 mb-0"></i>
                        </div>
                        <div>
                            <div class="text-uppercase small text-body-secondary fw-bold" style="font-size: 0.65rem;">Ubicación Actual</div>
                            <div class="h5 mb-0 fw-bold">{{ $allocation?->resource->name ?? 'Sin jaula asignada' }}</div>
                        </div>
                    </div>
                    <div class="p-3 border rounded-3 bg-white shadow-sm d-flex align-items-center" style="min-width: 140px;">
                        <div class="bg-warning-subtle text-warning rounded-circle p-2 me-3">
                            <i class="bi bi-stopwatch-fill h4 mb-0"></i>
                        </div>
                        <div>
                            <div class="text-uppercase small text-body-secondary fw-bold" style="font-size: 0.65rem;">Tiempo en Proceso</div>
                            <div class="h5 mb-0 fw-mono" x-text="timer">00:00:00</div>
                        </div>
                    </div>
                </div>

                <h6 class="text-uppercase small text-body-secondary fw-bold mb-3">Ejecución Profesional</h6>
                <div class="agenda-service-grid">
                    @php($acceptedQuote = $booking->quotes->firstWhere('status', 'accepted'))
                    @foreach($acceptedQuote?->items ?? $booking->services as $item)
                        <div class="card bg-light border-0 mb-2">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold text-primary">{{ $item->service->name }}</div>
                                        <small class="text-body-secondary">{{ $item->service->code }}</small>
                                    </div>
                                    <div class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAssignProfessional{{ $item->id }}">
                                            <i class="bi bi-person-badge me-1"></i> Asignar
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2 small">
                                    @if($item->operator_id)
                                        <span class="badge bg-white text-dark border"><i class="bi bi-person-check-fill text-success me-1"></i> {{ $item->operator->name }}</span>
                                    @else
                                        <span class="text-body-secondary italic">Sin profesional asignado</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="p-3 border rounded-3 bg-light">
                    <h6 class="text-uppercase small text-body-secondary fw-bold mb-3">Eventos de Bitácora</h6>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalAddEvent">
                            <i class="bi bi-camera me-1"></i> Registrar Suceso / Foto
                        </button>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6 class="text-uppercase small text-body-secondary fw-bold mb-3">Cierre Operativo</h6>
                    <form action="{{ route('agenda.update', $booking) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="btn btn-success w-100 py-3 fw-bold" data-confirm="¿Finalizar toda la atención y generar estado de cuenta final?">
                            <i class="bi bi-check2-circle me-1"></i> FINALIZAR SERVICIO
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Example: Assign Professional -->
@foreach($acceptedQuote?->items ?? [] as $item)
<div class="modal fade" id="modalAssignProfessional{{ $item->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('agenda.items.assign', [$booking, $item]) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Asignar Profesional: {{ $item->service->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-body-secondary mb-3">Elige al responsable de ejecutar este servicio específico.</p>
                
                <div class="mb-3">
                    <label class="form-label">Especialista / Operador</label>
                    <select name="operator_id" class="form-select" required>
                        <option value="">Selecciona un profesional...</option>
                        @foreach($operators as $operator)
                            <option value="{{ $operator->id }}" @selected($item->operator_id == $operator->id)>
                                {{ $operator->name }} {{ $operator->specialty ? "({$operator->specialty})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_external" value="1" id="ext{{ $item->id }}" @checked($item->is_external)>
                    <label class="form-check-label" for="ext{{ $item->id }}">
                        Es servicio externo (Ej: Cremación, Anestesia externa)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Confirmar Asignación</button>
            </div>
        </form>
    </div>
</div>
@endforeach
