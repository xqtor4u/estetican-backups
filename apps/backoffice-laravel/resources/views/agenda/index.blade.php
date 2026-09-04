@php
    $screenDebugId = 'AgInd';
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')
@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('agenda.create') }}" class="btn btn-dark rounded-pill px-4">
            <i class="bi bi-calendar-plus me-1"></i> Programar Reservación
        </a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    <section class="catalog-overview mb-4">
        <div class="catalog-overview__grid">
            <article class="catalog-overview-card catalog-overview-card--primary">
                <span class="catalog-overview-card__eyebrow">Agenda Operativa</span>
                <div class="catalog-overview-card__value">{{ $agendaOverviewCount }}</div>
                <p class="catalog-overview-card__text">{{ $operationalDateLabel }} con visión unificada de {{ $hotelModuleEnabled ? 'SPA y Hotel' : 'SPA' }} para coordinación centralizada.</p>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Carga estimada</span>
                <div class="catalog-overview-card__value-sm">{{ intdiv($totalEstimatedMinutes, 60) }}h {{ $totalEstimatedMinutes % 60 }}m</div>
                <div class="catalog-overview-card__label">{{ $scheduledCount }} sesiones activas en esta lectura</div>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Ventana operativa</span>
                <div class="catalog-overview-card__value-sm">
                    @if($firstScheduledAt && $lastScheduledEndAt)
                        {{ $firstScheduledAt->format($timeFormat) }} - {{ $lastScheduledEndAt->format($timeFormat) }}
                    @else
                        Sin carga visible
                    @endif
                </div>
                <div class="catalog-overview-card__label">${{ number_format($estimatedRevenue, 2) }} estimados · {{ $petsWithAgenda }} mascotas</div>
            </article>
        </div>
    </section>

    @if($calView === 'day')
    {{-- Estos botones envían el mismo <form> de filtros de abajo (form="agenda-filters-form"),
    en vez de navegar por su cuenta con un <a href> — antes eran dos paneles desconectados:
    cambiar "Ventana" acá perdía cualquier casilla de Estado marcada sin aplicar todavía.
    Este hidden va ANTES que los botones a propósito: al enviarse el form, si se hizo clic en
    un botón con name="date_scope" su valor queda después en el query string y gana (último
    valor gana con clave repetida) — si en cambio se envía con "Aplicar" (sin name), solo
    queda este valor, preservando la ventana activa. --}}
    <input type="hidden" id="agenda-date-scope-hidden" name="date_scope" value="{{ $dateScope }}" form="agenda-filters-form">
    <section class="agenda-scope-switch mb-3" aria-label="Ventana operativa de agenda">
        <button type="submit" form="agenda-filters-form" name="date_scope" value="today" class="agenda-scope-switch__item {{ $dateScope === 'today' ? 'agenda-scope-switch__item--active' : '' }}">Hoy</button>
        <button type="submit" form="agenda-filters-form" name="date_scope" value="tomorrow" class="agenda-scope-switch__item {{ $dateScope === 'tomorrow' ? 'agenda-scope-switch__item--active' : '' }}">Mañana</button>
        <button type="submit" form="agenda-filters-form" name="date_scope" value="all" class="agenda-scope-switch__item {{ $dateScope === 'all' ? 'agenda-scope-switch__item--active' : '' }}">Próximas</button>
        <button type="submit" form="agenda-filters-form" name="date_scope" value="full" class="agenda-scope-switch__item {{ $dateScope === 'full' ? 'agenda-scope-switch__item--active' : '' }}">Todas</button>
        <span class="agenda-scope-switch__hint">{{ $hotelModuleEnabled ? 'Agenda unificada: SPA y Hotel integrados para lectura rápida.' : 'Agenda de SPA.' }}</span>
    </section>

    @if(in_array($dateScope, ['today', 'tomorrow', 'custom'], true))
    <div class="agenda-calendar-nav mb-3">
        <button type="submit" form="agenda-filters-form" name="date_scope" value="custom" data-agenda-nav-date="{{ $selectedDate->copy()->subDay()->format('Y-m-d') }}" class="btn btn-sm btn-outline-dark agenda-nav-date-btn">&laquo; Día anterior</button>
        <button type="submit" form="agenda-filters-form" name="date_scope" value="today" class="btn btn-sm btn-outline-secondary">Hoy</button>
        <button type="submit" form="agenda-filters-form" name="date_scope" value="custom" data-agenda-nav-date="{{ $selectedDate->copy()->addDay()->format('Y-m-d') }}" class="btn btn-sm btn-outline-dark agenda-nav-date-btn">Día siguiente &raquo;</button>
        <span class="agenda-calendar-nav__label">{{ $operationalDateLabel }}</span>
    </div>
    @endif
    @endif

    <section class="agenda-scope-switch mb-3" aria-label="Vista de calendario">
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'cal_view', 'date_scope']), ['cal_view' => 'day', 'date_scope' => 'custom', 'date' => $selectedDateInput])) }}" class="agenda-scope-switch__item {{ $calView === 'day' ? 'agenda-scope-switch__item--active' : '' }}">Día</a>
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'cal_view']), ['cal_view' => 'week', 'date' => $selectedDateInput])) }}" class="agenda-scope-switch__item {{ $calView === 'week' ? 'agenda-scope-switch__item--active' : '' }}">Semana</a>
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'cal_view']), ['cal_view' => 'month', 'date' => $selectedDateInput])) }}" class="agenda-scope-switch__item {{ $calView === 'month' ? 'agenda-scope-switch__item--active' : '' }}">Mes</a>
        <span class="agenda-scope-switch__hint">Presentación estilo calendario — día, semana o mes.</span>
    </section>

    <x-list-filters id="agenda-filters-form" :action="route('agenda.index')" :reset-url="route('agenda.index')">
        <input type="hidden" name="cal_view" value="{{ $calView }}">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Buscar</label>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Mascota, cliente o servicio">
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="d-flex align-items-center justify-content-between">
                <label class="form-label d-block mb-0">Estado</label>
                <button type="button" id="agenda-status-toggle-all" class="btn btn-link btn-sm p-0">Marcar todos</button>
            </div>
            <input type="hidden" name="status_touched" value="1">
            <div class="d-flex flex-wrap gap-2 mt-1" id="agenda-status-checkboxes">
                <div class="form-check form-check-inline mb-0">
                    <input type="checkbox" class="form-check-input" name="status[]" value="scheduled" id="status-scheduled" @checked(in_array('scheduled', $statuses, true))>
                    <label class="form-check-label" for="status-scheduled">Programados</label>
                </div>
                <div class="form-check form-check-inline mb-0">
                    <input type="checkbox" class="form-check-input" name="status[]" value="work_order" id="status-work_order" @checked(in_array('work_order', $statuses, true))>
                    <label class="form-check-label" for="status-work_order">En proceso</label>
                </div>
                <div class="form-check form-check-inline mb-0">
                    <input type="checkbox" class="form-check-input" name="status[]" value="completed" id="status-completed" @checked(in_array('completed', $statuses, true))>
                    <label class="form-check-label" for="status-completed">Completados</label>
                </div>
                <div class="form-check form-check-inline mb-0">
                    <input type="checkbox" class="form-check-input" name="status[]" value="cancelled" id="status-cancelled" @checked(in_array('cancelled', $statuses, true))>
                    <label class="form-check-label" for="status-cancelled">Cancelados</label>
                </div>
                <div class="form-check form-check-inline mb-0">
                    <input type="checkbox" class="form-check-input" name="status[]" value="no_show" id="status-no_show" @checked(in_array('no_show', $statuses, true))>
                    <label class="form-check-label" for="status-no_show">No se presentó</label>
                </div>
                <div class="form-check form-check-inline mb-0">
                    <input type="checkbox" class="form-check-input" name="status[]" value="unfulfillable" id="status-unfulfillable" @checked(in_array('unfulfillable', $statuses, true))>
                    <label class="form-check-label" for="status-unfulfillable">No realizables</label>
                </div>
            </div>
            <div class="form-text">Ninguno marcado = mostrar todos.</div>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Fecha operativa</label>
            <input type="date" name="date" id="agenda-date-field" value="{{ $selectedDateInput }}" class="form-control">
            <div class="form-text">{{ $calView === 'day' ? 'Cambiarla y darle Aplicar la usa como ventana ("Fecha elegida").' : 'Salta a la semana o mes que contiene esta fecha.' }}</div>
        </div>
        <div class="col-lg-2 col-md-12">
            <div class="catalog-filter-note">
                <span class="catalog-filter-note__kicker">Lectura diaria</span>
                <p class="catalog-filter-note__text mb-0">La ventana operativa permite revisar por día o brincar a próximas sesiones sin mezclar aún otras líneas de negocio.</p>
            </div>
        </div>
    </x-list-filters>

    {{-- Orden: SPA primero (uso principal, cuestión práctica), Hotel después.
    Antes la sección de Hotel duplicaba SPA con la tabla de abajo (mismas citas
    del día, dos veces en la misma página, sin ninguna razón real). El SPA ya
    vive solo en la tabla (ordenable, paginada, con las mismas alertas); la
    sección de tarjetas quedó solo para Hotel, que la tabla no cubre (es
    consulta SpaBooking-only). --}}
    @if($calView === 'day')
    <x-list-table :paginator="$bookings">
        <thead>
            <tr>
                <th>
                    <x-sortable-header-link route="agenda.index" column="date" label="Fecha" :sort="$sort" :direction="$direction" :query="request()->except(['sort', 'direction', 'page'])" />
                </th>
                <th>Ventana</th>
                <th>
                    <x-sortable-header-link route="agenda.index" column="pet" label="Mascota" :sort="$sort" :direction="$direction" :query="request()->except(['sort', 'direction', 'page'])" />
                </th>
                <th>
                    <x-sortable-header-link route="agenda.index" column="client" label="Cliente" :sort="$sort" :direction="$direction" :query="request()->except(['sort', 'direction', 'page'])" />
                </th>
                <th>Servicios</th>
                <th>
                    <x-sortable-header-link route="agenda.index" column="status" label="Estado" :sort="$sort" :direction="$direction" :query="request()->except(['sort', 'direction', 'page'])" />
                </th>
                <th>Duración</th>
                <th>
                    <x-sortable-header-link route="agenda.index" column="total" label="Total" :sort="$sort" :direction="$direction" :query="request()->except(['sort', 'direction', 'page'])" />
                </th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $booking)
                @php
                    $canActOnLines = in_array($booking->status, ['scheduled', 'work_order'], true);
                    // El tag de servicio y el badge de estado siguen siendo interactivos sólo sin
                    // alerta (decisión SYNC-053). El pop-up con el desglose por línea, en cambio,
                    // también aplica a una cita `overdue` — de ahí que $lineTplUrl no dependa de la alerta.
                    $rowActionable = ! $booking->alert_reason && $canActOnLines;
                    $lineState = fn ($l) => $l->cancelled_at ? 'cancelled' : ($l->not_performed_at ? 'not_performed' : ($l->completed_at ? 'completed' : ($l->started_at ? 'in_progress' : 'pending')));
                    $lineTplUrl = $canActOnLines ? route('agenda.services.update', ['booking' => $booking, 'line' => '__LINE__']) : null;
                @endphp
                <tr>
                    <td>
                        <div class="catalog-stat">{{ $booking->scheduled_at?->format($datetimeFormat) }}</div>
                        <div class="catalog-stat__hint">{{ $booking->scheduled_at?->diffForHumans() }}</div>
                    </td>
                    <td>
                        <div class="catalog-stat">{{ $booking->time_window_label ?? 'Sin rango' }}</div>
                        <div class="catalog-stat__hint">bloque estimado</div>
                    </td>
                    <td>
                        <div class="catalog-title-stack__title">
                            {{ $booking->pet?->name }}
                            @if($booking->order_folio)
                                <span class="badge bg-light text-dark border ms-1" style="font-size: 0.65rem; font-weight: 700;" title="Folio de orden de trabajo — el mismo número que se imprime en recibo, orden de trabajo y presupuesto.">{{ $booking->order_folio }}</span>
                            @endif
                        </div>
                        <div class="catalog-title-stack__description">{{ $booking->pet?->species_label ?: 'Sin especie' }}</div>
                    </td>
                    <td>
                        <div class="catalog-title-stack__title">{{ trim(($booking->pet?->client?->first_name ?? '') . ' ' . ($booking->pet?->client?->last_name ?? '')) }}</div>
                    </td>
                    <td>
                        <div class="catalog-inline-tags agenda-service-tags">
                            @foreach($booking->services as $bookingService)
                                @php
                                    // Color por departamento: `spa` = Estética, `vaccine`/`clinica` = Veterinaria,
                                    // el resto (hotel/extra/combo) queda neutro.
                                    $svcType = $bookingService->service?->type;
                                    $deptClass = $svcType === 'spa' ? 'agenda-service-tag--spa'
                                        : (in_array($svcType, ['vaccine', 'clinica'], true) ? 'agenda-service-tag--vet' : '');
                                @endphp
                                @if($rowActionable)
                                    <button type="button"
                                        class="catalog-inline-tag agenda-service-trigger {{ $deptClass }}"
                                        data-bs-toggle="modal" data-bs-target="#agendaQuickActions"
                                        data-mode="service"
                                        data-status="{{ $booking->status }}"
                                        data-pet="{{ $booking->pet?->name }}"
                                        data-folio="{{ $booking->order_folio }}"
                                        data-detail-url="{{ route('agenda.show', $booking) }}"
                                        data-line-url-tpl="{{ $lineTplUrl }}"
                                        data-services="{{ collect([[
                                            'id' => $bookingService->id,
                                            'name' => $bookingService->service?->name ?? 'Servicio',
                                            'state' => $lineState($bookingService),
                                        ]])->toJson() }}"
                                        title="Acciones de este servicio">
                                        {{ $bookingService->service?->name ?? 'Servicio' }} <i class="bi bi-chevron-down" style="font-size: 0.6em;"></i>
                                    </button>
                                @else
                                    <span class="catalog-inline-tag {{ $deptClass }}">{{ $bookingService->service?->name ?? 'Servicio' }}</span>
                                @endif
                            @endforeach
                        </div>
                    </td>
                    <td>
                        @php
                            $alertReason = $booking->alert_reason;
                            $unpaidWhenCompleted = !$alertReason && $booking->status === 'completed' && $booking->unpaidBalance() > 0;
                            $statusLabel = $alertReason ? match ($alertReason) {
                                'not_started' => 'No se ha iniciado',
                                'overdue' => 'Sin cerrar',
                                'future' => 'Fecha inválida',
                            } : match ($booking->status) {
                                'scheduled' => 'Programado',
                                'work_order' => 'En proceso',
                                'completed' => 'Completado',
                                'cancelled' => 'Cancelado',
                                'no_show' => 'No se presentó',
                                'unfulfillable' => 'No realizado',
                                default => ucfirst(str_replace('_', ' ', $booking->status)),
                            };
                            $badgeClasses = [
                                'catalog-status-badge',
                                'agenda-alert-badge' => $alertReason,
                                'agenda-status-badge--completed' => !$alertReason && $booking->status === 'completed',
                                'agenda-status-badge--work-order' => !$alertReason && $booking->status === 'work_order',
                                'agenda-status-badge--no-show' => !$alertReason && $booking->status === 'no_show',
                                'agenda-status-badge--unfulfillable' => !$alertReason && $booking->status === 'unfulfillable',
                                'agenda-status-badge--scheduled' => !$alertReason && $booking->status === 'scheduled',
                                'agenda-status-badge--cancelled' => !$alertReason && $booking->status === 'cancelled',
                            ];
                        @endphp
                        @if($rowActionable)
                            <button type="button"
                                @class([...$badgeClasses, 'agenda-status-trigger', 'border-0'])
                                data-bs-toggle="modal" data-bs-target="#agendaQuickActions"
                                data-mode="booking"
                                data-status="{{ $booking->status }}"
                                data-label="{{ $statusLabel }}"
                                data-pet="{{ $booking->pet?->name }}"
                                data-folio="{{ $booking->order_folio }}"
                                data-detail-url="{{ route('agenda.show', $booking) }}"
                                data-start-url="{{ route('agenda.start', $booking) }}"
                                data-update-url="{{ route('agenda.update', $booking) }}"
                                data-unfulfillable-url="{{ route('agenda.unfulfillable', $booking) }}"
                                data-line-url-tpl="{{ $lineTplUrl }}"
                                data-services="{{ $booking->services->map(fn ($l) => [
                                    'id' => $l->id,
                                    'name' => $l->service?->name ?? 'Servicio',
                                    'state' => $lineState($l),
                                ])->values()->toJson() }}"
                                title="Acciones rápidas de la cita">
                                {{ $statusLabel }} <i class="bi bi-chevron-down ms-1" style="font-size: 0.7em;"></i>
                            </button>
                        @else
                            <span @class($badgeClasses)>
                                {{ $statusLabel }}
                                @if($unpaidWhenCompleted)
                                    <span class="text-danger fw-bold" title="Completado sin registro de pago — saldo pendiente ${{ number_format($booking->unpaidBalance(), 2) }}">*</span>
                                @endif
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="catalog-stat">{{ $booking->estimated_duration_minutes }} min</div>
                        <div class="catalog-stat__hint">estimados</div>
                    </td>
                    <td>
                        @php
                            // unpaidBalance()/totalPaid() suman CashLedger/BankLedger (vía presupuesto
                            // aceptado) + Payment directo (móvil) — antes solo sumaba lo primero, así
                            // que toda cita cobrada desde móvil sin Quote de por medio (el camino más
                            // usado en producción real) mostraba el saldo completo aunque sí estuviera
                            // pagada, y nunca se pintaba en rojo.
                            $paid = $booking->totalPaid();
                            $balance = $booking->unpaidBalance();
                            $balanceHint = match (true) {
                                $paid > 0 && $balance <= 0                  => 'cobrado · $' . number_format($paid, 2),
                                $paid > 0                                   => 'saldo · pagado $' . number_format($paid, 2),
                                $booking->status === 'completed'            => 'por cobrar',
                                default                                     => 'estimado',
                            };
                        @endphp
                        <div class="catalog-stat {{ $balance > 0 ? 'text-danger' : ($paid > 0 ? 'text-success' : '') }}">${{ number_format($balance, 2) }}</div>
                        <div class="catalog-stat__hint">{{ $balanceHint }}</div>
                    </td>
                    <td class="text-end">
                        {{-- Acciones rápidas contextuales al estado (SYNC-082). Chips compactos con
                        color congruente con la app móvil. "Iniciar" y "No asistió" son de un clic
                        ("No asistió" pasa por el confirm global `data-confirm`); "Terminar" abre el
                        pop-up `#agendaQuickActions` con todas las opciones de cierre (y cobro / por
                        línea / no realizada). "Cancelar" no vive aquí — necesita motivo obligatorio,
                        está en el Detalle. --}}
                        @php $alert = $booking->alert_reason; @endphp
                        <div class="agenda-chips justify-content-end">
                            @can('editar agenda')
                                @if($alert === 'future')
                                    <a href="{{ route('agenda.edit', $booking) }}" class="agenda-chip agenda-chip--neutral"><i class="bi bi-pencil"></i> Corregir fecha</a>
                                @elseif($alert === 'not_started' || (! $alert && $booking->status === 'scheduled'))
                                    <form method="POST" action="{{ route('agenda.start', $booking) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="agenda-chip agenda-chip--start"><i class="bi bi-play-fill"></i> Iniciar</button>
                                    </form>
                                    {{-- Una cita retrasada sigue en estado `scheduled` (la alerta es derivada),
                                    así que todavía se puede reprogramar — `agenda.update` solo lo permite
                                    mientras esté Programada. --}}
                                    <a href="{{ route('agenda.edit', $booking) }}" class="agenda-chip agenda-chip--neutral"><i class="bi bi-calendar-event"></i> Reprogramar</a>
                                    <form method="POST" action="{{ route('agenda.no-show', $booking) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="agenda-chip agenda-chip--noshow" data-confirm="¿Marcar esta cita como No se presentó?"><i class="bi bi-person-x"></i> No asistió</button>
                                    </form>
                                @elseif($alert === 'overdue' || (! $alert && $booking->status === 'work_order'))
                                    <button type="button"
                                        class="agenda-chip agenda-chip--done"
                                        data-bs-toggle="modal" data-bs-target="#agendaQuickActions"
                                        data-mode="booking"
                                        data-status="{{ $booking->status }}"
                                        data-label="{{ $statusLabel }}"
                                        data-pet="{{ $booking->pet?->name }}"
                                        data-folio="{{ $booking->order_folio }}"
                                        data-detail-url="{{ route('agenda.show', $booking) }}"
                                        data-start-url="{{ route('agenda.start', $booking) }}"
                                        data-update-url="{{ route('agenda.update', $booking) }}"
                                        data-unfulfillable-url="{{ route('agenda.unfulfillable', $booking) }}"
                                        data-line-url-tpl="{{ $lineTplUrl }}"
                                        data-services="{{ $booking->services->map(fn ($l) => [
                                            'id' => $l->id,
                                            'name' => $l->service?->name ?? 'Servicio',
                                            'state' => $lineState($l),
                                        ])->values()->toJson() }}"
                                        title="Terminar la cita">
                                        <i class="bi bi-check2-circle"></i> Terminar
                                    </button>
                                @elseif(! $alert && in_array($booking->status, ['cancelled', 'no_show', 'unfulfillable'], true))
                                    <a href="{{ route('agenda.edit', $booking) }}" class="agenda-chip agenda-chip--neutral"><i class="bi bi-calendar-event"></i> Reprogramar</a>
                                @endif
                            @endcan

                            @if(! $alert && $booking->status === 'completed' && $balance > 0)
                                @can('cobros.registrar')
                                    <a href="{{ route('agenda.show', ['booking' => $booking, 'cobrar' => 1]) }}" class="agenda-chip agenda-chip--pay"><i class="bi bi-cash-coin"></i> Cobrar</a>
                                @endcan
                            @endif

                            <a href="{{ route('agenda.show', $booking) }}" class="agenda-chip agenda-chip--ghost">Detalle</a>
                            @can('ver mascotas')
                                <a href="{{ route('pets.show', ['pet' => $booking->pet, 'view' => 'blocks']) }}" class="agenda-chip agenda-chip--ghost">Mascota</a>
                            @endcan
                            @can('ver clientes')
                                <a href="{{ route('clients.show', $booking->pet?->client) }}" class="agenda-chip agenda-chip--ghost">Cliente</a>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-4 text-body-secondary">Aún no hay servicios programados visibles en agenda.</td>
                </tr>
            @endforelse
        </tbody>
    </x-list-table>

    @if($hotelModuleEnabled)
    <section class="card shadow-sm border-0 mb-4 mt-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
                <div>
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Lectura operativa</div>
                    <h2 class="h4 mb-2">Estancias de Hotel</h2>
                    <p class="text-body-secondary mb-0">Reservaciones de Hotel activas en esta ventana — SPA se lee en la tabla de arriba.</p>
                </div>
                <span class="badge rounded-pill text-bg-light border">{{ $operationalDateLabel }}</span>
            </div>

            @if(($blockedToday ?? collect())->isNotEmpty())
                <div class="alert alert-secondary d-flex flex-column gap-1 mb-3">
                    <strong>Operadores no disponibles hoy:</strong>
                    @foreach($blockedToday as $w)
                        <span>🔒 {{ $w->operator?->full_name ?? 'Operador #'.$w->operator_id }} — {{ $w->starts_at->format('H:i') }}–{{ $w->ends_at->format('H:i') }}{{ $w->reason ? ' ('.$w->reason.')' : '' }}</span>
                    @endforeach
                </div>
            @endif

            <div class="agenda-timeline">
                @forelse($timelineBookings->where('agenda_type', 'hotel') as $booking)
                    @php
                        $isHotel = $booking instanceof \App\Models\HotelReservation;
                        $detailUrl = $isHotel ? route('hotel-reservations.show', $booking) : route('agenda.show', $booking);
                    @endphp
                    <article class="agenda-timeline-item {{ $isHotel ? 'agenda-timeline-item--hotel' : '' }}"
                        style="cursor: pointer;"
                        onclick="if(!event.target.closest('a,button')) window.location='{{ $detailUrl }}'">
                        <div class="agenda-timeline-item__time">
                            <div class="agenda-timeline-item__time-main">{{ $booking->time_window_label ?? $booking->scheduled_at?->format($timeFormat) }}</div>
                            <div class="agenda-timeline-item__time-sub">
                                @if(!$isHotel)
                                    {{ $booking->estimated_duration_minutes }} min estimados
                                @else
                                    Estancia Hotel
                                @endif
                            </div>
                        </div>
                        <div class="agenda-timeline-item__body">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        @if($isHotel)
                                            <span class="badge bg-info text-white fw-bold" style="font-size: 0.75rem;">HOTEL</span>
                                        @else
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold" style="font-size: 0.75rem;">SPA</span>
                                        @endif
                                        <div class="catalog-title-stack__title mb-0">{{ $booking->pet?->name ?: 'Mascota sin nombre' }}</div>
                                        @if($booking->order_folio)
                                            <span class="badge bg-light text-dark border" style="font-size: 0.65rem; font-weight: 700;">{{ $booking->order_folio }}</span>
                                        @endif
                                    </div>
                                    <div class="catalog-title-stack__description">{{ trim(($booking->pet?->client?->first_name ?? '') . ' ' . ($booking->pet?->client?->last_name ?? '')) ?: 'Cliente sin nombre visible' }}</div>
                                </div>
                                @php $alertReason = $isHotel ? null : $booking->alert_reason; @endphp
                                <span @class([
                                    'catalog-status-badge',
                                    'agenda-alert-badge' => $alertReason,
                                    'agenda-status-badge--completed' => !$alertReason && $booking->status === 'completed',
                                    'agenda-status-badge--work-order' => !$alertReason && $booking->status === 'work_order',
                                    'agenda-status-badge--no-show' => !$alertReason && $booking->status === 'no_show',
                                    'agenda-status-badge--unfulfillable' => !$alertReason && $booking->status === 'unfulfillable',
                                    'agenda-status-badge--scheduled' => !$alertReason && $booking->status === 'scheduled',
                                    'agenda-status-badge--cancelled' => !$alertReason && $booking->status === 'cancelled',
                                ])>
                                    {{ $alertReason ? match ($alertReason) {
                                        'not_started' => 'No se ha iniciado',
                                        'overdue' => 'Sin cerrar',
                                        'future' => 'Fecha inválida',
                                    } : match ($booking->status) {
                                        'scheduled' => 'Programado',
                                        'work_order' => 'En proceso',
                                        'completed' => 'Completado',
                                        'cancelled' => 'Cancelado',
                                        'no_show' => 'No se presentó',
                                        'unfulfillable' => 'No realizado',
                                        default => ucfirst(str_replace('_', ' ', $booking->status)),
                                    } }}
                                </span>
                            </div>

                            <div class="catalog-inline-tags mt-2">
                                @if(!$isHotel)
                                    @foreach($booking->services as $bookingService)
                                        <span class="catalog-inline-tag">{{ $bookingService->service?->name ?? 'Servicio' }}</span>
                                    @endforeach
                                @else
                                    <span class="catalog-inline-tag">Hospedaje / Estancia</span>
                                @endif
                            </div>

                            @if($booking->notes)
                                <p class="text-body-secondary small mb-0 mt-2">{{ $booking->notes }}</p>
                            @endif
                        </div>
                        <div class="agenda-timeline-item__meta">
                            <div class="catalog-stat">
                                @if(!$isHotel)
                                    ${{ number_format((float) $booking->total_estimated_price, 2) }}
                                @else
                                    --
                                @endif
                            </div>
                            <div class="catalog-stat__hint">{{ !$isHotel ? 'estimado' : 'hotel cost' }}</div>
                            <div class="catalog-actions-cluster mt-2">
                                @if(!$isHotel)
                                    <a href="{{ $detailUrl }}" class="btn btn-sm btn-outline-dark">Detalle</a>
                                @else
                                    <a href="{{ $detailUrl }}" class="btn btn-sm btn-outline-info">Ver Estancia</a>
                                @endif
                                @can('ver mascotas')
                                    <a href="{{ route('pets.show', ['pet' => $booking->pet, 'view' => 'blocks']) }}" class="btn btn-sm btn-outline-primary">Mascota</a>
                                @endcan
                                @can('ver clientes')
                                    <a href="{{ route('clients.show', $booking->pet?->client) }}" class="btn btn-sm btn-outline-secondary">Cliente</a>
                                @endcan
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="text-center py-4 text-body-secondary">No hay estancias de hotel activas en esta ventana.</div>
                @endforelse
            </div>
        </div>
    </section>
    @endif
    @elseif($calView === 'week')
        @include('agenda.partials._calendar_week')
    @elseif($calView === 'month')
        @include('agenda.partials._calendar_month')
    @endif
</div>

<style>
.agenda-timeline-item--hotel {
    background-color: rgba(13, 202, 240, 0.05);
    border-left: 3px solid #0dcaf0;
}
/* Mismo ámbar que ya usa la app móvil para "en proceso"/"programada" atípica —
   requiere revisión ahora, a diferencia de los demás badges de estado (ya resueltos). */
.agenda-alert-badge {
    background-color: #f59e0b !important;
    color: #fff !important;
    border-color: #f59e0b !important;
    animation: agenda-alert-pulse 1.6s ease-in-out infinite;
}
@keyframes agenda-alert-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.55; }
}
</style>

<script nonce="{{ csp_nonce() }}">
(function () {
    var toggleBtn = document.getElementById('agenda-status-toggle-all');
    var container = document.getElementById('agenda-status-checkboxes');
    if (!toggleBtn || !container) return;
    var checkboxes = container.querySelectorAll('input[type="checkbox"]');

    function allChecked() {
        return Array.prototype.every.call(checkboxes, function (cb) { return cb.checked; });
    }

    toggleBtn.addEventListener('click', function () {
        var checkAll = !allChecked();
        checkboxes.forEach(function (cb) { cb.checked = checkAll; });
    });
})();

(function () {
    var dateScopeHidden = document.getElementById('agenda-date-scope-hidden');
    var dateField = document.getElementById('agenda-date-field');
    if (dateField && dateScopeHidden) {
        dateField.addEventListener('change', function () {
            dateScopeHidden.value = 'custom';
        });
    }
    document.querySelectorAll('.agenda-nav-date-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (dateField && btn.dataset.agendaNavDate) {
                dateField.value = btn.dataset.agendaNavDate;
            }
        });
    });
})();
</script>

{{-- Acciones rápidas al hacer click en el tag de estado de una cita Programada / En proceso --}}
<div class="modal fade" id="agendaQuickActions" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="agendaQuickActionsTitle">Acciones de la cita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-body-secondary small mb-3" id="agendaQuickActionsSubtitle"></p>
                <div class="alert alert-danger py-2 px-3 small d-none" id="agendaQuickActionsError"></div>
                <div class="d-grid gap-2" id="agendaQuickActionsButtons"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="agendaQuickActionsClose">Volver a la agenda</button>
            </div>
        </div>
    </div>
</div>

<style>
.agenda-status-trigger { cursor: pointer; }
.agenda-status-trigger:hover { filter: brightness(0.95); }

/* Chips de acción rápida de la columna Acciones (SYNC-082) — pastilla compacta,
   letra chica, color congruente con los contenedores tonales de la app móvil. */
.agenda-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.28rem;
    align-items: center;
}
.agenda-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1.2;
    border: 1px solid transparent;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
    -webkit-appearance: none;
    appearance: none;
    transition: filter .12s ease, background-color .12s ease;
}
.agenda-chip:hover { filter: brightness(0.96); text-decoration: none; }
.agenda-chip i { font-size: 0.9em; }
.agenda-chip--start   { background: rgba(59, 130, 246, 0.14); color: #1d4ed8; border-color: rgba(59, 130, 246, 0.28); }
.agenda-chip--done    { background: rgba(40, 167, 69, 0.16); color: #17643a; border-color: rgba(40, 167, 69, 0.30); }
.agenda-chip--pay     { background: rgba(245, 158, 11, 0.16); color: #92510a; border-color: rgba(245, 158, 11, 0.32); }
.agenda-chip--noshow  { background: rgba(239, 68, 68, 0.14); color: #b91c1c; border-color: rgba(239, 68, 68, 0.28); }
.agenda-chip--neutral { background: rgba(108, 117, 125, 0.14); color: #535b63; border-color: rgba(108, 117, 125, 0.28); }
.agenda-chip--ghost   { background: transparent; color: #374151; font-weight: 800; border-color: rgba(108, 117, 125, 0.35); }
.agenda-chip--ghost:hover { background: rgba(108, 117, 125, 0.10); }
button.agenda-service-trigger {
    cursor: pointer;
    font: inherit;
    line-height: inherit;
    -webkit-appearance: none;
    appearance: none;
}
button.agenda-service-trigger:hover { filter: brightness(0.95); text-decoration: underline; }
</style>

<script nonce="{{ csp_nonce() }}">
(function () {
    var modalEl = document.getElementById('agendaQuickActions');
    if (!modalEl) return;
    var titleEl = document.getElementById('agendaQuickActionsTitle');
    var subtitleEl = document.getElementById('agendaQuickActionsSubtitle');
    var buttonsEl = document.getElementById('agendaQuickActionsButtons');
    var csrf = @json(csrf_token());
    var OPERATORS = @json(($operators ?? collect())->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->values());

    var STATE = {
        pending:       { label: 'Pendiente',   badge: 'text-bg-secondary' },
        in_progress:   { label: 'En proceso',  badge: 'text-bg-warning' },
        completed:     { label: 'Completado',  badge: 'text-bg-success' },
        not_performed: { label: 'No realizado', badge: 'text-bg-danger' },
        cancelled:     { label: 'Cancelado',   badge: 'text-bg-dark' }
    };

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text != null) e.textContent = text;
        return e;
    }

    function bookingForm(action, label, cssClass, hidden) {
        var f = el('form', 'd-grid'); f.method = 'POST'; f.action = action;
        var t = el('input'); t.type = 'hidden'; t.name = '_token'; t.value = csrf; f.appendChild(t);
        (hidden || []).forEach(function (h) {
            var i = el('input'); i.type = 'hidden'; i.name = h.name; i.value = h.value; f.appendChild(i);
        });
        var b = el('button', 'btn ' + cssClass, label); b.type = 'submit'; f.appendChild(b);
        return f;
    }

    function linkButton(href, label, cssClass) {
        var a = el('a', 'btn ' + cssClass, label); a.href = href; return a;
    }

    // --- Acciones por línea de servicio, sin cerrar el pop-up -------------------
    // Antes cada botón era un <form> que hacía submit nativo → recarga y el modal
    // se perdía; en una cita con 2+ servicios no se podía accionar el 2º sin
    // reabrirlo. Ahora van por fetch() y el panel se vuelve a pintar en su lugar.
    var panelDirty = false;      // ¿se cambió algo? → al cerrar, recargar la agenda
    var panelWrap = null;        // contenedor del panel de servicios (se re-pinta)
    var panelTplUrl = null;      // plantilla de URL con `__LINE__`

    function errorEl() { return document.getElementById('agendaQuickActionsError'); }
    function showPanelError(msg) {
        var e = errorEl(); if (!e) return;
        e.textContent = msg; e.classList.remove('d-none');
    }
    function clearPanelError() {
        var e = errorEl(); if (e) { e.textContent = ''; e.classList.add('d-none'); }
    }

    function submitLineAction(url, fields) {
        var body = new URLSearchParams();
        body.set('_token', csrf);
        body.set('_method', 'PATCH');
        (fields || []).forEach(function (p) { body.set(p[0], p[1]); });
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString()
        }).then(function (r) {
            return r.json().then(function (j) { return { ok: r.ok, data: j }; })
                .catch(function () { return { ok: r.ok, data: {} }; });
        });
    }

    function lineActionButton(url, fields, label, cssClass) {
        var b = el('button', 'btn btn-sm ' + cssClass, label);
        b.type = 'button';
        b.addEventListener('click', function () {
            clearPanelError();
            b.disabled = true;
            submitLineAction(url, fields).then(function (res) {
                if (res.ok && res.data && res.data.ok) {
                    panelDirty = true;
                    if (res.data.lines) rerenderPanel(res.data.lines);
                } else {
                    showPanelError((res.data && res.data.message) || 'No se pudo actualizar el servicio.');
                    b.disabled = false;
                }
            }).catch(function () {
                showPanelError('Error de red — vuelve a intentar.');
                b.disabled = false;
            });
        });
        return b;
    }

    function reassignRow(url) {
        var wrap = el('div', 'input-group input-group-sm mt-1');
        var sel = el('select', 'form-select form-select-sm');
        var o0 = el('option', null, 'Reasignar operador…'); o0.value = ''; sel.appendChild(o0);
        OPERATORS.forEach(function (op) {
            var o = el('option', null, op.name); o.value = op.id; sel.appendChild(o);
        });
        var b = el('button', 'btn btn-sm btn-outline-secondary', 'Reasignar'); b.type = 'button';
        b.addEventListener('click', function () {
            if (!sel.value) return;
            clearPanelError();
            b.disabled = true;
            submitLineAction(url, [['operator_id', sel.value]]).then(function (res) {
                if (res.ok && res.data && res.data.ok) {
                    panelDirty = true;
                    if (res.data.lines) rerenderPanel(res.data.lines);
                } else {
                    showPanelError((res.data && res.data.message) || 'No se pudo reasignar.');
                    b.disabled = false;
                }
            }).catch(function () { showPanelError('Error de red.'); b.disabled = false; });
        });
        wrap.appendChild(sel); wrap.appendChild(b);
        return wrap;
    }

    function buildPanel(services) {
        var box = el('div', 'border rounded p-2 mb-2');
        if (services.length > 1) {
            box.appendChild(el('div', 'text-uppercase small text-body-secondary fw-semibold mb-2', 'Servicios de la cita'));
        }
        services.forEach(function (s) {
            var url = panelTplUrl.replace('__LINE__', s.id);
            var st = STATE[s.state] || STATE.pending;
            var row = el('div', 'd-flex flex-column gap-1 py-2 border-top');

            var head = el('div', 'd-flex justify-content-between align-items-center');
            head.appendChild(el('span', 'small fw-semibold', s.name));
            head.appendChild(el('span', 'badge ' + st.badge, st.label));
            row.appendChild(head);

            var actions = el('div', 'd-flex flex-wrap gap-1 align-items-center');
            if (s.state === 'pending') {
                actions.appendChild(lineActionButton(url, [['mark_started', '1']], 'Iniciar', 'btn-primary'));
                actions.appendChild(lineActionButton(url, [['mark_not_performed', '1']], 'No se realizó', 'btn-link link-danger text-decoration-none'));
            } else if (s.state === 'in_progress') {
                actions.appendChild(lineActionButton(url, [['mark_completed', '1']], 'Completar', 'btn-success'));
                actions.appendChild(lineActionButton(url, [['mark_not_performed', '1']], 'No se realizó', 'btn-link link-danger text-decoration-none'));
            } else if (s.state === 'not_performed' || s.state === 'cancelled') {
                actions.appendChild(lineActionButton(url, [['mark_reactivate', '1']], 'Reactivar', 'btn-outline-primary'));
            } else if (s.state === 'completed') {
                actions.appendChild(el('span', 'small text-success', '✓ Servicio terminado'));
            }
            row.appendChild(actions);

            if (s.state !== 'not_performed' && s.state !== 'cancelled') {
                row.appendChild(reassignRow(url));
            }
            box.appendChild(row);
        });
        return box;
    }

    function serviceLinePanel(tplUrl, services) {
        panelTplUrl = tplUrl;
        panelWrap = el('div');
        panelWrap.appendChild(buildPanel(services));
        return panelWrap;
    }

    function rerenderPanel(lines) {
        if (!panelWrap || !panelTplUrl) return;
        panelWrap.innerHTML = '';
        panelWrap.appendChild(buildPanel(lines));
    }

    function populate(t) {
        if (!t) return;
        var status = t.dataset.status;
        var mode = t.dataset.mode || 'booking';
        var pet = t.dataset.pet || 'la mascota';
        var folio = t.dataset.folio ? ' · ' + t.dataset.folio : '';
        var services = [];
        try { services = JSON.parse(t.dataset.services || '[]'); } catch (e) { services = []; }
        buttonsEl.innerHTML = '';
        panelWrap = null; panelTplUrl = null;
        clearPanelError();

        // Click en el tag de UN servicio concreto: solo las acciones de esa línea.
        if (mode === 'service' && services.length && t.dataset.lineUrlTpl) {
            titleEl.textContent = services[0].name + ' — ' + pet;
            subtitleEl.textContent = '¿Qué quieres hacer con este servicio?';
            buttonsEl.appendChild(serviceLinePanel(t.dataset.lineUrlTpl, services));
            buttonsEl.appendChild(linkButton(t.dataset.detailUrl, 'Abrir detalle de la cita', 'btn-outline-dark'));
            return;
        }

        if (status === 'work_order' || status === 'scheduled') {
            titleEl.textContent = (status === 'work_order' ? 'En proceso — ' : 'Programada — ') + pet + folio;
            subtitleEl.textContent = 'Actúa sobre cada servicio, o cierra la cita completa.';

            if (services.length && t.dataset.lineUrlTpl) {
                buttonsEl.appendChild(serviceLinePanel(t.dataset.lineUrlTpl, services));
            }

            if (status === 'scheduled') {
                buttonsEl.appendChild(bookingForm(t.dataset.startUrl, 'Iniciar toda la cita', 'btn-primary fw-bold'));
            } else {
                buttonsEl.appendChild(bookingForm(t.dataset.updateUrl, 'Terminar y cobrar', 'btn-success fw-bold', [
                    { name: '_method', value: 'PUT' }, { name: 'status', value: 'completed' }, { name: 'cobrar', value: '1' }
                ]));
                buttonsEl.appendChild(bookingForm(t.dataset.updateUrl, 'Solo terminar (cobrar después)', 'btn-outline-success', [
                    { name: '_method', value: 'PUT' }, { name: 'status', value: 'completed' }
                ]));
                if (t.dataset.unfulfillableUrl) {
                    buttonsEl.appendChild(bookingForm(t.dataset.unfulfillableUrl, 'Marcar no realizada', 'btn-outline-warning'));
                }
            }
            buttonsEl.appendChild(linkButton(t.dataset.detailUrl, 'Abrir detalle', 'btn-outline-dark'));
            buttonsEl.appendChild(linkButton(t.dataset.detailUrl, 'Reprogramar o cancelar la cita', 'btn-outline-secondary'));
        } else {
            titleEl.textContent = t.dataset.label || 'Cita';
            subtitleEl.textContent = '';
            buttonsEl.appendChild(linkButton(t.dataset.detailUrl, 'Abrir detalle', 'btn-outline-dark'));
        }
    }

    // Abrimos el modal explícitamente en vez de depender del data-api de Bootstrap
    // (`data-bs-toggle`), que en este bundle de Vite no siempre se engancha.
    var modalInstance = null;
    function openModal(trigger) {
        panelDirty = false;
        populate(trigger);
        var BS = window.bootstrap;
        if (BS && BS.Modal) {
            modalInstance = modalInstance || new BS.Modal(modalEl);
            modalInstance.show();
            return;
        }
        // Último recurso sin Bootstrap JS: mostrar el modal a mano.
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        if (!document.querySelector('.modal-backdrop.agenda-fallback')) {
            var bd = document.createElement('div');
            bd.className = 'modal-backdrop fade show agenda-fallback';
            bd.addEventListener('click', closeFallback);
            document.body.appendChild(bd);
        }
    }
    function closeFallback() {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        var bd = document.querySelector('.modal-backdrop.agenda-fallback');
        if (bd) bd.remove();
    }

    // Al cerrar el pop-up, si se accionó alguna línea por AJAX hay que recargar la
    // agenda para que la fila refleje el nuevo estado (los cambios se hicieron sin
    // recarga). "Volver a la agenda" hace lo mismo, explícito.
    function dismissModal() {
        if (panelDirty) { window.location.reload(); return; }
        var BS = window.bootstrap;
        if (BS && BS.Modal) {
            (modalInstance || BS.Modal.getInstance(modalEl) || new BS.Modal(modalEl)).hide();
        } else {
            closeFallback();
        }
    }
    modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            if (panelDirty) { e.preventDefault(); e.stopPropagation(); window.location.reload(); return; }
            if (!window.bootstrap || !window.bootstrap.Modal) closeFallback();
        });
    });
    var closeBtn = document.getElementById('agendaQuickActionsClose');
    if (closeBtn) closeBtn.addEventListener('click', dismissModal);
    modalEl.addEventListener('hidden.bs.modal', function () { if (panelDirty) window.location.reload(); });

    document.querySelectorAll('[data-bs-target="#agendaQuickActions"]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openModal(trigger);
        });
    });

    // Si el data-api de Bootstrap SÍ funciona, esto rellena el cuerpo antes de mostrarlo.
    modalEl.addEventListener('show.bs.modal', function (ev) {
        if (ev.relatedTarget) populate(ev.relatedTarget);
    });
})();
</script>
@endsection