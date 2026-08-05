@php($screenDebugId = 'HomInd')
@extends('layouts.app')

@section('title', 'Inicio — EstetiCAN Backoffice')

@section('content')

<div class="dashboard-root">

    {{-- ENCABEZADO --}}
    <div class="dashboard-header mb-3">
        <div>
            <p class="text-muted mb-0 text-capitalize">{{ $dayName }}, {{ $dateFormatted }}</p>
            <h1 class="fw-bold mb-0">Panel de control</h1>
        </div>
    </div>

    {{-- AGENDA HERO — corazón del sistema --}}
    @can('ver agenda')
    <a href="{{ route('agenda.index') }}" class="agenda-hero-banner mb-4 d-block text-decoration-none">
        <div class="agenda-hero-inner">
            <div class="agenda-hero-left">
                <div class="agenda-hero-icon"><i class="bi bi-calendar3-week"></i></div>
                <div>
                    <div class="agenda-hero-title">Agenda del día</div>
                    <div class="agenda-hero-sub">
                        {{ $spaCounts['total'] }} cita{{ $spaCounts['total'] !== 1 ? 's' : '' }} SPA
                        @if($spaCounts['work_order'] > 0)
                            &nbsp;·&nbsp; <span class="agenda-hero-badge">{{ $spaCounts['work_order'] }} en proceso</span>
                        @endif
                        @can('ver hotel')
                            @if($hotelActive > 0)
                                &nbsp;·&nbsp; {{ $hotelActive }} en hotel
                            @endif
                        @endcan
                    </div>
                </div>
            </div>
            <div class="agenda-hero-arrow"><i class="bi bi-arrow-right-circle-fill"></i></div>
        </div>
    </a>
    @endcan

    {{-- KPIs PRINCIPALES --}}
    <div class="row g-3 mb-4">

        {{-- Citas SPA hoy --}}
        @can('ver agenda')
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-spa">
                <div class="kpi-icon"><i class="bi bi-scissors"></i></div>
                <div class="kpi-value">{{ $spaCounts['total'] }}</div>
                <div class="kpi-label">Citas SPA hoy</div>
                <div class="kpi-sub">
                    <span class="badge bg-warning text-dark">{{ $spaCounts['scheduled'] }} prog.</span>
                    <span class="badge bg-primary">{{ $spaCounts['work_order'] }} en proc.</span>
                    <span class="badge bg-success">{{ $spaCounts['completed'] }} listas</span>
                </div>
            </div>
        </div>
        @endcan

        {{-- Hotel activo --}}
        @if($hotelModuleEnabled)
            @can('ver hotel')
            <div class="col-6 col-md-3">
                <div class="kpi-card kpi-hotel">
                    <div class="kpi-icon"><i class="bi bi-house-heart"></i></div>
                    <div class="kpi-value">{{ $hotelActive }}</div>
                    <div class="kpi-label">Huéspedes en Hotel</div>
                    <div class="kpi-sub">
                        <a href="{{ route('hotel-reservations.index') }}" class="text-decoration-none small">Ver estancias →</a>
                    </div>
                </div>
            </div>
            @endcan
        @endif

        {{-- Clientes --}}
        @can('ver clientes')
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-clients">
                <div class="kpi-icon"><i class="bi bi-people"></i></div>
                <div class="kpi-value">{{ number_format($totalClients) }}</div>
                <div class="kpi-label">Clientes registrados</div>
                <div class="kpi-sub">
                    <a href="{{ route('clients.index') }}" class="text-decoration-none small">Ver directorio →</a>
                </div>
            </div>
        </div>
        @endcan

        {{-- Mascotas --}}
        @can('ver mascotas')
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-pets">
                <div class="kpi-icon"><i class="bi bi-heart"></i></div>
                <div class="kpi-value">{{ number_format($totalPets) }}</div>
                <div class="kpi-label">Mascotas registradas</div>
                <div class="kpi-sub">
                    <a href="{{ route('pets.index') }}" class="text-decoration-none small">Ver catálogo →</a>
                </div>
            </div>
        </div>
        @endcan

    </div>

    <div class="row g-4">

        {{-- Próximas citas --}}
        @can('ver agenda')
        <div class="col-lg-7">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-header">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-clock me-2 text-muted"></i>Próximas citas</h5>
                    <a href="{{ route('agenda.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Agenda completa</a>
                </div>
                <div class="dashboard-card-body p-0">
                    @foreach($upcomingBookings as $booking)
                    <div class="upcoming-booking-row">
                        <div class="upcoming-time">
                            {{ $booking->scheduled_at->format($timeFormat) }}
                            <span class="text-muted small d-block">{{ $booking->scheduled_at->format('d/m') }}</span>
                        </div>
                        <div class="upcoming-info">
                            <div class="fw-semibold">{{ $booking->pet->name ?? '—' }}</div>
                            <div class="text-muted small">{{ $booking->pet->client->full_name ?? 'Sin cliente' }}</div>
                        </div>
                        <div class="ms-auto">
                            @if($booking->status === 'scheduled')
                                <span class="badge bg-warning text-dark">Programada</span>
                            @elseif($booking->status === 'work_order')
                                <span class="badge bg-primary text-white">En proceso</span>
                            @elseif($booking->status === 'completed')
                                <span class="badge bg-success text-white">Completada</span>
                            @elseif($booking->status === 'no_show')
                                <span class="badge bg-danger text-white">No show</span>
                            @else
                                <span class="badge bg-secondary text-white">{{ $booking->status }}</span>
                            @endif
                        </div>
                        <a href="{{ route('agenda.show', $booking) }}" class="btn btn-sm btn-outline-dark rounded-pill ms-2">
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    @endforeach
                    @if($upcomingBookings->isEmpty())
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                        Sin citas próximas
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endcan

        {{-- Panel derecho: Ingresos + Accesos rápidos --}}
        <div class="col-lg-5">

            {{-- Ingresos del día --}}
            @if(auth()->user()->is_super_admin)
            <div class="dashboard-card mb-4">
                <div class="dashboard-card-header">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-cash-stack me-2 text-muted"></i>Ingresos hoy</h5>
                </div>
                <div class="dashboard-card-body">
                    <div class="income-total">
                        ${{ number_format($incomeToday, 2) }}
                    </div>
                    <div class="d-flex gap-3 mt-2">
                        <div class="income-breakdown">
                            <i class="bi bi-cash text-success me-1"></i>
                            <span class="text-muted small">Caja</span>
                            <strong class="d-block">${{ number_format($cashToday, 2) }}</strong>
                        </div>
                        <div class="income-breakdown">
                            <i class="bi bi-bank text-primary me-1"></i>
                            <span class="text-muted small">Banco</span>
                            <strong class="d-block">${{ number_format($bankToday, 2) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Accesos rápidos --}}
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-lightning-charge me-2 text-muted"></i>Accesos rápidos</h5>
                </div>
                <div class="dashboard-card-body">
                    <div class="quick-links">
                        @can('crear agenda')
                        <a href="{{ route('agenda.create') }}" class="quick-link-btn quick-link-spa">
                            <i class="bi bi-scissors"></i>
                            <span>Nueva cita SPA</span>
                        </a>
                        @endcan
                        @if($hotelModuleEnabled)
                            @can('crear hotel')
                            <a href="{{ route('hotel-reservations.create') }}" class="quick-link-btn quick-link-hotel">
                                <i class="bi bi-house-heart"></i>
                                <span>Nueva estancia Hotel</span>
                            </a>
                            @endcan
                        @endif
                        @can('crear clientes')
                        <a href="{{ route('clients.create') }}" class="quick-link-btn">
                            <i class="bi bi-person-plus"></i>
                            <span>Nuevo cliente</span>
                        </a>
                        @endcan
                        @can('ver catalogo_servicios')
                        <a href="{{ route('services.index') }}" class="quick-link-btn">
                            <i class="bi bi-grid"></i>
                            <span>Servicios</span>
                        </a>
                        @endcan
                        @can('ver operadores')
                        <a href="{{ route('operators.index') }}" class="quick-link-btn">
                            <i class="bi bi-person-badge"></i>
                            <span>Operadores</span>
                        </a>
                        @endcan
                        @if(auth()->user()->is_super_admin)
                        <a href="{{ route('system-settings.index') }}" class="quick-link-btn">
                            <i class="bi bi-gear"></i>
                            <span>Configuración</span>
                        </a>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.dashboard-root {
    padding: 1.5rem 0;
}
.dashboard-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

/* Agenda Hero Banner */
.agenda-hero-banner {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
    border-radius: 1rem;
    padding: 1.1rem 1.5rem;
    transition: transform .15s, box-shadow .15s;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
}
.agenda-hero-banner:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,.2);
}
.agenda-hero-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.agenda-hero-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.agenda-hero-icon {
    font-size: 2rem;
    color: #f59e0b;
    line-height: 1;
}
.agenda-hero-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
}
.agenda-hero-sub {
    font-size: .85rem;
    color: rgba(255,255,255,.6);
    margin-top: .15rem;
}
.agenda-hero-badge {
    background: rgba(245,158,11,.25);
    color: #fbbf24;
    border-radius: .3rem;
    padding: .05rem .35rem;
    font-weight: 600;
}
.agenda-hero-arrow {
    font-size: 1.75rem;
    color: rgba(255,255,255,.35);
    transition: color .15s, transform .15s;
}
.agenda-hero-banner:hover .agenda-hero-arrow {
    color: #f59e0b;
    transform: translateX(4px);
}

/* KPI Cards */
.kpi-card {
    border-radius: 1rem;
    padding: 1.25rem;
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    border: 1px solid rgba(0,0,0,.06);
    background: #fff;
    transition: transform .15s, box-shadow .15s;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,.07);
}
.kpi-icon {
    font-size: 1.4rem;
    margin-bottom: 0.25rem;
    opacity: .7;
}
.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}
.kpi-label {
    font-size: .8rem;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.kpi-sub {
    margin-top: .4rem;
    display: flex;
    flex-wrap: wrap;
    gap: .25rem;
}

.kpi-spa   { border-top: 3px solid #f59e0b; }
.kpi-hotel { border-top: 3px solid #8b5cf6; }
.kpi-clients { border-top: 3px solid #3b82f6; }
.kpi-pets  { border-top: 3px solid #ec4899; }

.kpi-spa   .kpi-icon { color: #f59e0b; }
.kpi-hotel .kpi-icon { color: #8b5cf6; }
.kpi-clients .kpi-icon { color: #3b82f6; }
.kpi-pets  .kpi-icon { color: #ec4899; }

/* Dashboard Cards */
.dashboard-card {
    background: #fff;
    border-radius: 1rem;
    border: 1px solid rgba(0,0,0,.06);
    overflow: hidden;
}
.dashboard-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(0,0,0,.06);
}
.dashboard-card-body {
    padding: 1.25rem;
}

/* Upcoming bookings */
.upcoming-booking-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .75rem 1.25rem;
    border-bottom: 1px solid rgba(0,0,0,.04);
    transition: background .1s;
}
.upcoming-booking-row:last-child { border-bottom: none; }
.upcoming-booking-row:hover { background: rgba(0,0,0,.02); }
.upcoming-time {
    font-weight: 700;
    font-size: .95rem;
    min-width: 2.5rem;
    text-align: center;
}
.upcoming-info { flex: 1; min-width: 0; }

/* Income */
.income-total {
    font-size: 2.2rem;
    font-weight: 800;
    color: #111;
    line-height: 1;
}
.income-breakdown {
    flex: 1;
    background: rgba(0,0,0,.03);
    border-radius: .5rem;
    padding: .5rem .75rem;
}

/* Quick links */
.quick-links {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .5rem;
}
.quick-link-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .3rem;
    padding: .75rem .5rem;
    border-radius: .75rem;
    background: rgba(0,0,0,.03);
    text-decoration: none;
    color: #333;
    font-size: .78rem;
    font-weight: 500;
    text-align: center;
    transition: background .15s, transform .1s;
}
.quick-link-btn i {
    font-size: 1.3rem;
    opacity: .8;
}
.quick-link-btn:hover {
    background: rgba(0,0,0,.08);
    color: #000;
    transform: translateY(-1px);
}
.quick-link-spa { border: 1.5px solid rgba(245,158,11,.3); }
.quick-link-spa i { color: #f59e0b; }
.quick-link-spa:hover { background: rgba(245,158,11,.08); border-color: #f59e0b; }
.quick-link-hotel { border: 1.5px solid rgba(139,92,246,.3); }
.quick-link-hotel i { color: #8b5cf6; }
.quick-link-hotel:hover { background: rgba(139,92,246,.08); border-color: #8b5cf6; }
</style>
@endsection
