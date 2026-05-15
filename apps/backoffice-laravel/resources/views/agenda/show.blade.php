@php
    $screenDebugId = 'AgSho';
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')
@php($pet = $booking->pet)
@php($client = $pet?->client)
@php($acceptedQuote = $booking->quotes->firstWhere('status', 'accepted') ?? $booking->quotes->sortByDesc('created_at')->first())

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('agenda.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver a agenda
        </a>
        <a href="{{ route('pets.show', $pet) }}" class="btn btn-outline-dark">
            <i class="bi bi-paw me-1"></i> Perfil de mascota
        </a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    @php($isOverdue = $booking->scheduled_at?->isPast() && in_array($booking->status, ['scheduled', 'work_order']))
    
    @if($isOverdue)
        <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3 p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; flex-shrink: 0;">
                    <i class="bi bi-alarm-fill h4 mb-0"></i>
                </div>
                <div>
                    <h4 class="h5 mb-1 fw-bold">Atención: Cita Vencida</h4>
                    <p class="mb-0 opacity-75">Esta sesión estaba programada para el {{ $booking->scheduled_at?->format('d/m/Y H:i') }} y aún no se ha cerrado.</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('agenda.edit', $booking) }}" class="btn btn-warning fw-bold text-dark border-0">
                    <i class="bi bi-calendar-range me-1"></i> Reprogramar
                </a>
                @if($booking->status === 'work_order')
                    <form action="{{ route('agenda.update', $booking) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="bi bi-check-circle me-1"></i> Cerrar Ahora
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif
    <!-- Operational Stepper -->
    <div class="card shadow-sm border-0 mb-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="d-flex flex-column flex-lg-row align-items-stretch text-center border-bottom">
                @php($steps = [
                    'scheduled' => ['label' => 'Programado', 'icon' => 'calendar-event', 'color' => 'secondary'],
                    'quote_draft' => ['label' => 'Presupuesto', 'icon' => 'calculator', 'color' => 'primary', 'active_if' => $booking->quotes_count > 0 && $booking->status === 'scheduled'],
                    'work_order' => ['label' => 'En Proceso', 'icon' => 'tools', 'color' => 'warning'],
                    'completed' => ['label' => 'Finalizado', 'icon' => 'check-circle-fill', 'color' => 'success'],
                ])
                
                @foreach($steps as $key => $step)
                    @php($isActive = ($booking->status === $key) || ($key === 'quote_draft' && $booking->quotes_count > 0 && $booking->status === 'scheduled'))
                    @php($isDone = ($booking->status === 'completed' && $key !== 'completed') || ($booking->status === 'work_order' && ($key === 'scheduled' || $key === 'quote_draft')))
                    
                    <div class="flex-grow-1 p-3 border-end d-flex align-items-center justify-content-center gap-2 {{ $isActive ? 'bg-light fw-bold' : ($isDone ? 'text-success opacity-75' : 'text-body-secondary opacity-50') }}">
                        <i class="bi bi-{{ $isDone ? 'check-circle-fill' : ($step['icon'] ?? 'circle') }} h5 mb-0"></i>
                        <span>{{ $step['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Quick Info Grid -->
    <section class="catalog-overview mb-4">
        <div class="catalog-overview__grid">
            <article class="catalog-overview-card catalog-overview-card--primary d-flex align-items-center gap-3">
                <div class="rounded-circle overflow-hidden shadow-sm border border-2 border-white" style="width: 56px; height: 56px; flex-shrink: 0; background: rgba(0,0,0,0.05);">
                    @php($photoUrl = $pet?->catalog_photo_url ? parse_url($pet->catalog_photo_url, PHP_URL_PATH) : null)
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $pet->name ?? 'Mascota' }}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="align-items-center justify-content-center h-100" style="display: none;">
                             <img src="https://ui-avatars.com/api/?name={{ urlencode($pet->name ?? 'P') }}&color=7F9CF5&background=EBF4FF" class="w-100 h-100">
                        </div>
                    @else
                        <div class="d-flex align-items-center justify-content-center h-100">
                             <i class="bi bi-paw-fill text-dark opacity-25" style="font-size: 1.2rem;"></i>
                        </div>
                    @endif
                </div>
                <div>
                    <span class="catalog-overview-card__eyebrow">Paciente</span>
                    <div class="h5 mb-0 fw-bold">{{ $pet?->name }}</div>
                    <p class="mb-0 small opacity-75">{{ $client?->full_name }}</p>
                </div>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Horario</span>
                <div class="catalog-overview-card__value-sm">{{ $booking->scheduled_at?->format(config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A') }} <small>hs</small></div>
                <div class="catalog-overview-card__label">{{ $booking->scheduled_at?->translatedFormat('d M Y') }}</div>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Gestión</span>
                <div class="catalog-overview-card__value-sm">{{ $booking->quotes_count }} <small>Opcs.</small></div>
                <div class="catalog-overview-card__label">Presupuestos</div>
            </article>
            <article class="catalog-overview-card {{ $booking->status === 'completed' ? 'border-success border-2' : '' }}">
                <span class="catalog-overview-card__eyebrow">Balance</span>
                @php($totalPaid = $acceptedQuote ? ($client?->payments()->where('payable_id', $acceptedQuote->id)->sum('amount') ?? 0) : 0)
                @php($balance = ($acceptedQuote?->total_amount ?? 0) - $totalPaid)
                <div class="catalog-overview-card__value-sm text-{{ $balance > 0 ? 'danger' : 'success' }}">${{ number_format($balance, 2) }}</div>
                <div class="catalog-overview-card__label">{{ $balance > 0 ? 'Por liquidar' : 'Pagado completo' }}</div>
            </article>
        </div>
    </section>

    <div class="row g-4">
        <!-- Main Content (Dynamic based on state) -->
        <div class="col-lg-8">
            @if($booking->status === 'scheduled')
                @include('agenda.partials._quote_manager')
            @elseif($booking->status === 'work_order')
                @include('agenda.partials._work_order')
            @elseif($booking->status === 'completed')
                @include('agenda.partials._billing_summary')
            @endif

            <!-- Notes & Background -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3">Información Adicional</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-body-secondary small fw-bold text-uppercase d-block mb-1">Notas de Agenda</label>
                            <div class="p-3 bg-light rounded-3 italic">
                                {{ $booking->notes ?: 'Sin notas registradas.' }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-body-secondary small fw-bold text-uppercase d-block mb-1">Alertas Médicas</label>
                            @if($pet?->medicalAlerts?->isNotEmpty())
                                @foreach($pet->medicalAlerts as $alert)
                                    <div class="alert alert-danger py-2 px-3 mb-1 small d-flex align-items-center">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ $alert->alert_text }}
                                    </div>
                                @endforeach
                            @else
                                <div class="p-3 bg-light rounded-3 text-body-secondary italic">Sin alertas médicas.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <!-- Sidebar Actions & Context -->
        <div class="col-lg-4">
            <!-- Printing Documents -->
            <div class="card shadow-sm border-0 mb-3 overflow-hidden border-start border-primary border-4">
                <div class="card-body">
                    <h5 class="h6 text-uppercase text-body-secondary fw-bold mb-3">Documentos de Impresión</h5>
                    <div class="d-grid gap-2">
                        @if($acceptedQuote)
                            <a href="{{ route('reports.quote', $acceptedQuote) }}" target="_blank" class="btn btn-light btn-sm text-start d-flex align-items-center">
                                <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>
                                <span>{{ $acceptedQuote->status === 'accepted' ? 'Imprimir Presupuesto' : 'Imprimir Borrador' }}</span>
                            </a>
                        @endif

                        @if($booking->status === 'work_order')
                            <a href="{{ route('reports.work-order', $booking) }}" target="_blank" class="btn btn-light btn-sm text-start d-flex align-items-center">
                                <i class="bi bi-tools me-2 text-warning"></i>
                                <span>Imprimir Orden de Trabajo</span>
                            </a>
                        @endif

                        @if($booking->status === 'completed')
                            <a href="{{ route('reports.invoice', $booking) }}" target="_blank" class="btn btn-light btn-sm text-start d-flex align-items-center">
                                <i class="bi bi-receipt me-2 text-success"></i>
                                <span>Imprimir Recibo de Pago</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3 overflow-hidden">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0 small text-uppercase">Logísticas de Estancia</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                            <i class="bi bi-box-seam h4 mb-0"></i>
                        </div>
                        <div>
                            @php($resourceAllocation = $booking->resourceAllocations->firstWhere('allocation_type', 'reserved'))
                            <div class="fw-bold">{{ $resourceAllocation?->resource?->name ?? 'Sin asignar' }}</div>
                            <div class="small text-body-secondary">Jaula / Espacio Operativo</div>
                        </div>
                    </div>
                    
                    @if($booking->status === 'scheduled')
                        <button type="button" class="btn btn-outline-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#modalChangeResource">
                            Cambiar asignación
                        </button>
                    @endif
                </div>
            </div>

            <!-- Client Summary Mini-Card -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body p-4">
                    <h5 class="h6 text-uppercase text-body-secondary fw-bold mb-3">Titular / Cliente</h5>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi bi-person h4 mb-0 opacity-50"></i>
                        </div>
                        <div>
                            <div class="fw-bold">{{ $client?->full_name }}</div>
                            <div class="small text-body-secondary">{{ $client?->phone ?? 'Sin teléfono' }}</div>
                        </div>
                    </div>
                    @if($client?->email)
                        <div class="bg-light p-2 rounded-3 text-center small mb-3">
                            <i class="bi bi-envelope me-1"></i> {{ $client->email }}
                        </div>
                    @endif
                    <div class="d-grid">
                        <a href="mailto:{{ $client?->email }}" class="btn btn-outline-dark btn-sm">Contactar</a>
                    </div>
                </div>
            </div>

            @if($booking->status === 'scheduled')
                <!-- Secondary Actions -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-4">
                        <h5 class="h6 text-uppercase text-body-secondary fw-bold mb-3">Gestión de Cita</h5>
                        <div class="d-grid gap-2">
                             <a href="{{ route('agenda.edit', $booking) }}" class="btn btn-outline-warning btn-sm border-2 fw-bold text-dark" style="border-color: #ffc107 !important;">
                                 <i class="bi bi-calendar-range me-1"></i> Reprogramar Cita
                             </a>
                             <hr class="my-2">
                             <button type="button" class="btn btn-link link-danger btn-sm text-start p-0" data-bs-toggle="modal" data-bs-target="#modalCancel">
                                <i class="bi bi-x-circle me-1"></i> Cancelar por completo...
                             </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@if($booking->status === 'scheduled')
    <!-- Modal: Cancel -->
    <div class="modal fade" id="modalCancel" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('agenda.cancel', $booking) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Cancelar Sesión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de cancelar esta sesión? Esta acción liberará la jaula {{ $resourceAllocation?->resource?->code }} y el horario.</p>
                    <div class="mb-3">
                        <label class="form-label">Motivo de cancelación</label>
                        <textarea name="cancellation_reason" class="form-control" rows="3" required placeholder="Ej: Cliente canceló por mensaje, mascota indispuesta..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Mantener cita</button>
                    <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
                </div>
            </form>
        </div>
    </div>
@endif

@endsection