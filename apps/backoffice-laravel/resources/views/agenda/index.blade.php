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
                <div class="catalog-overview-card__value">{{ $bookings->total() + $timelineBookings->where('agenda_type', 'hotel')->count() }}</div>
                <p class="catalog-overview-card__text">{{ $operationalDateLabel }} con visión unificada de SPA y Hotel para coordinación centralizada.</p>
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

    <section class="agenda-scope-switch mb-3" aria-label="Ventana operativa de agenda">
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'date_scope', 'date']), ['date_scope' => 'today'])) }}" class="agenda-scope-switch__item {{ $dateScope === 'today' ? 'agenda-scope-switch__item--active' : '' }}">Hoy</a>
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'date_scope', 'date']), ['date_scope' => 'tomorrow'])) }}" class="agenda-scope-switch__item {{ $dateScope === 'tomorrow' ? 'agenda-scope-switch__item--active' : '' }}">Mañana</a>
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'date_scope', 'date']), ['date_scope' => 'all'])) }}" class="agenda-scope-switch__item {{ $dateScope === 'all' ? 'agenda-scope-switch__item--active' : '' }}">Próximas</a>
        <a href="{{ route('agenda.index', array_merge(request()->except(['page', 'date_scope', 'date']), ['date_scope' => 'full'])) }}" class="agenda-scope-switch__item {{ $dateScope === 'full' ? 'agenda-scope-switch__item--active' : '' }}">Todas</a>
        <span class="agenda-scope-switch__hint">Agenda unificada: SPA y Hotel integrados para lectura rápida.</span>
    </section>

    <x-list-filters :action="route('agenda.index')" :reset-url="route('agenda.index')">
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Buscar</label>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Mascota, cliente o servicio">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Estado</label>
            <select name="status" class="form-select">
                <option value="active" @selected($status === 'active')>Activos (todos)</option>
                <option value="scheduled" @selected($status === 'scheduled')>Solo programados</option>
                <option value="work_order" @selected($status === 'work_order')>Solo en proceso</option>
                <option value="completed" @selected($status === 'completed')>Completados</option>
                <option value="all" @selected($status === 'all')>Todos</option>
                <option value="cancelled" @selected($status === 'cancelled')>Cancelados</option>
                <option value="no_show" @selected($status === 'no_show')>No show</option>
                <option value="unfulfillable" @selected($status === 'unfulfillable')>No realizables</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Ventana</label>
            <select name="date_scope" class="form-select">
                <option value="today" @selected($dateScope === 'today')>Hoy</option>
                <option value="tomorrow" @selected($dateScope === 'tomorrow')>Mañana</option>
                <option value="custom" @selected($dateScope === 'custom')>Fecha elegida</option>
                <option value="all" @selected($dateScope === 'all')>Próximas</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">Fecha operativa</label>
            <input type="date" name="date" value="{{ $selectedDateInput }}" class="form-control">
            <div class="form-text">Se usa cuando la ventana es `Fecha elegida`.</div>
        </div>
        <div class="col-lg-2 col-md-12">
            <div class="catalog-filter-note">
                <span class="catalog-filter-note__kicker">Lectura diaria</span>
                <p class="catalog-filter-note__text mb-0">La ventana operativa permite revisar por día o brincar a próximas sesiones sin mezclar aún otras líneas de negocio.</p>
            </div>
        </div>
    </x-list-filters>

    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
                <div>
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Lectura operativa</div>
                    <h2 class="h4 mb-2">Bloques horarios visibles</h2>
                    <p class="text-body-secondary mb-0">Esta vista ordena la carga de Agenda SPA por hora de entrada y duración estimada para lectura rápida de recepción y coordinación.</p>
                </div>
                <span class="badge rounded-pill text-bg-light border">{{ $operationalDateLabel }}</span>
            </div>

            <div class="agenda-timeline">
                @forelse($timelineBookings as $booking)
                    @php($isHotel = $booking instanceof \App\Models\HotelReservation)
                    @php($detailUrl = $isHotel ? route('hotel-reservations.show', $booking) : route('agenda.show', $booking))
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
                                    </div>
                                    <div class="catalog-title-stack__description">{{ trim(($booking->pet?->client?->first_name ?? '') . ' ' . ($booking->pet?->client?->last_name ?? '')) ?: 'Cliente sin nombre visible' }}</div>
                                </div>
                                <span class="catalog-status-badge {{ $booking->status === 'scheduled' ? 'catalog-status-badge--active' : 'catalog-status-badge--inactive' }}">
                                    {{ $booking->status === 'scheduled' ? 'Programado' : ucfirst(str_replace('_', ' ', $booking->status)) }}
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
                    <div class="text-center py-4 text-body-secondary">No hay bloques operativos visibles para esta ventana.</div>
                @endforelse
            </div>
        </div>
    </section>

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
                        <div class="catalog-title-stack__title">{{ $booking->pet?->name }}</div>
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
                        <span class="catalog-status-badge {{ $booking->status === 'scheduled' ? 'catalog-status-badge--active' : 'catalog-status-badge--inactive' }}">
                            {{ $booking->status === 'scheduled' ? 'Programado' : ucfirst(str_replace('_', ' ', $booking->status)) }}
                        </span>
                    </td>
                    <td>
                        <div class="catalog-stat">{{ $booking->estimated_duration_minutes }} min</div>
                        <div class="catalog-stat__hint">estimados</div>
                    </td>
                    <td>
                        @php
                            $aq = $booking->quotes->firstWhere('status', 'accepted');
                            $paid = $aq ? $aq->cashLedgers->sum('amount') + $aq->bankLedgers->sum('amount') : 0;
                            $total = $aq ? $aq->total_amount : (float) $booking->total_estimated_price;
                            $balance = $total - $paid;
                        @endphp
                        <div class="catalog-stat {{ $balance > 0 && $aq ? 'text-danger' : '' }}">${{ number_format($balance, 2) }}</div>
                        <div class="catalog-stat__hint">
                            @if($paid > 0)
                                saldo · pagado ${{ number_format($paid, 2) }}
                            @else
                                estimado
                            @endif
                        </div>
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
</div>

<style>
.agenda-timeline-item--hotel {
    background-color: rgba(13, 202, 240, 0.05);
    border-left: 3px solid #0dcaf0;
}
</style>
@endsection