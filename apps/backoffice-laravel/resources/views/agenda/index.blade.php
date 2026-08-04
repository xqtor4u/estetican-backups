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
    <section class="agenda-scope-switch mb-3" aria-label="Ventana operativa de agenda">
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'date_scope', 'date']), ['date_scope' => 'today'])) }}" class="agenda-scope-switch__item {{ $dateScope === 'today' ? 'agenda-scope-switch__item--active' : '' }}">Hoy</a>
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'date_scope', 'date']), ['date_scope' => 'tomorrow'])) }}" class="agenda-scope-switch__item {{ $dateScope === 'tomorrow' ? 'agenda-scope-switch__item--active' : '' }}">Mañana</a>
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'date_scope', 'date']), ['date_scope' => 'all'])) }}" class="agenda-scope-switch__item {{ $dateScope === 'all' ? 'agenda-scope-switch__item--active' : '' }}">Próximas</a>
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'date_scope', 'date']), ['date_scope' => 'full'])) }}" class="agenda-scope-switch__item {{ $dateScope === 'full' ? 'agenda-scope-switch__item--active' : '' }}">Todas</a>
        <span class="agenda-scope-switch__hint">{{ $hotelModuleEnabled ? 'Agenda unificada: SPA y Hotel integrados para lectura rápida.' : 'Agenda de SPA.' }}</span>
    </section>

    @if(in_array($dateScope, ['today', 'tomorrow', 'custom'], true))
    <div class="agenda-calendar-nav mb-3">
        <a class="btn btn-sm btn-outline-dark" href="{{ route('agenda.index', array_merge(request()->except(['page', 'date', 'date_scope']), ['date_scope' => 'custom', 'date' => $selectedDate->copy()->subDay()->format('Y-m-d')])) }}">&laquo; Día anterior</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('agenda.index', array_merge(request()->except(['page', 'date', 'date_scope']), ['date_scope' => 'today'])) }}">Hoy</a>
        <a class="btn btn-sm btn-outline-dark" href="{{ route('agenda.index', array_merge(request()->except(['page', 'date', 'date_scope']), ['date_scope' => 'custom', 'date' => $selectedDate->copy()->addDay()->format('Y-m-d')])) }}">Día siguiente &raquo;</a>
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

    <x-list-filters :action="route('agenda.index')" :reset-url="route('agenda.index')">
        <input type="hidden" name="cal_view" value="{{ $calView }}">
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
        @if($calView === 'day')
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Ventana</label>
            <select name="date_scope" class="form-select">
                <option value="today" @selected($dateScope === 'today')>Hoy</option>
                <option value="tomorrow" @selected($dateScope === 'tomorrow')>Mañana</option>
                <option value="custom" @selected($dateScope === 'custom')>Fecha elegida</option>
                <option value="all" @selected($dateScope === 'all')>Próximas</option>
            </select>
        </div>
        @endif
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Fecha operativa</label>
            <input type="date" name="date" value="{{ $selectedDateInput }}" class="form-control">
            <div class="form-text">{{ $calView === 'day' ? 'Se usa cuando la ventana es `Fecha elegida`.' : 'Salta a la semana o mes que contiene esta fecha.' }}</div>
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
                        <div class="catalog-inline-tags">
                            @foreach($booking->services as $bookingService)
                                <span class="catalog-inline-tag">{{ $bookingService->service?->name ?? 'Servicio' }}</span>
                            @endforeach
                        </div>
                    </td>
                    <td>
                        @php
                            $alertReason = $booking->alert_reason;
                            $unpaidWhenCompleted = !$alertReason && $booking->status === 'completed' && $booking->unpaidBalance() > 0;
                        @endphp
                        <span @class([
                            'catalog-status-badge',
                            'agenda-alert-badge' => $alertReason,
                            'agenda-status-badge--completed' => !$alertReason && $booking->status === 'completed',
                            'agenda-status-badge--work-order' => !$alertReason && $booking->status === 'work_order',
                            'agenda-status-badge--no-show' => !$alertReason && $booking->status === 'no_show',
                            'agenda-status-badge--unfulfillable' => !$alertReason && $booking->status === 'unfulfillable',
                            'catalog-status-badge--active' => !$alertReason && $booking->status === 'scheduled',
                            'catalog-status-badge--inactive' => !$alertReason && $booking->status === 'cancelled',
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
                            @if($unpaidWhenCompleted)
                                <span class="text-danger fw-bold" title="Completado sin registro de pago — saldo pendiente ${{ number_format($booking->unpaidBalance(), 2) }}">*</span>
                            @endif
                        </span>
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
                        @endphp
                        <div class="catalog-stat {{ $balance > 0 ? 'text-danger' : '' }}">${{ number_format($balance, 2) }}</div>
                        <div class="catalog-stat__hint">{{ $paid > 0 ? 'saldo · pagado $' . number_format($paid, 2) : 'estimado' }}</div>
                    </td>
                    <td class="text-end">
                        <div class="catalog-actions-cluster justify-content-end">
                            <a href="{{ route('agenda.show', $booking) }}" class="btn btn-sm btn-outline-dark">Detalle</a>
                            <a href="{{ route('pets.show', ['pet' => $booking->pet, 'view' => 'blocks']) }}" class="btn btn-sm btn-outline-primary">Mascota</a>
                            <a href="{{ route('clients.show', $booking->pet?->client) }}" class="btn btn-sm btn-outline-secondary">Cliente</a>
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
                                    'catalog-status-badge--active' => !$alertReason && $booking->status === 'scheduled',
                                    'catalog-status-badge--inactive' => !$alertReason && $booking->status === 'cancelled',
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
                                <a href="{{ route('pets.show', ['pet' => $booking->pet, 'view' => 'blocks']) }}" class="btn btn-sm btn-outline-primary">Mascota</a>
                                <a href="{{ route('clients.show', $booking->pet?->client) }}" class="btn btn-sm btn-outline-secondary">Cliente</a>
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
</script>
@endsection