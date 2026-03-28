@extends('layouts.app')

@php($screenDebugId = 'HomDas')

@section('content')
<section class="landing-hero position-relative overflow-hidden rounded-4 p-4 p-lg-5 mb-4">
    <div class="row align-items-center g-4 position-relative">
        <div class="col-lg-7">
            <span class="landing-chip mb-3">{{ config('backoffice.brand.shell_title', 'Backoffice operativo') }} 2026-03-25</span>
            <h1 class="display-5 fw-semibold mb-3">Clientes, catalogos y sucursales ya viven en una sola entrada operativa.</h1>
            <p class="lead text-white-50 mb-4">
                La portada ahora refleja el baseline real del sistema: flujo comercial operativo, catalogos base consistentes y preparacion clara para abrir agenda y recursos sin volver a fragmentar navegacion.
            </p>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('clients.index') }}" class="btn btn-light btn-lg">Ir a clientes</a>
                <a href="{{ route('services.index') }}" class="btn btn-outline-light btn-lg">Abrir servicios</a>
                <a href="{{ route('branches.index') }}" class="btn btn-outline-light btn-lg">Ver sucursales</a>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-sm-4">
                    <div class="landing-metric rounded-4 p-3 h-100">
                        <span class="landing-metric-label">Hoy operable</span>
                        <strong class="landing-metric-value">5 bloques</strong>
                        <small class="d-block text-white-50">Clientes, mascotas, servicios, operadores y sucursales.</small>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="landing-metric rounded-4 p-3 h-100">
                        <span class="landing-metric-label">Siguiente corte</span>
                        <strong class="landing-metric-value">Resources</strong>
                        <small class="d-block text-white-50">Disponibilidad, bloqueos y mantenimiento.</small>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="landing-metric rounded-4 p-3 h-100">
                        <span class="landing-metric-label">Criterio</span>
                        <strong class="landing-metric-value">Una sola ruta</strong>
                        <small class="d-block text-white-50">Navegacion comun arriba, accesos rapidos abajo.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="landing-panel rounded-4 p-4">
                <p class="text-uppercase small fw-semibold letter-space mb-3">Estado actual</p>
                <div class="d-grid gap-3">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold">Clientes y mascotas</span>
                            <span class="badge text-bg-success">Activo</span>
                        </div>
                        <small class="text-secondary-emphasis">CRUD operativo, listado raíz de mascotas, ficha especializada, fotos y alertas.</small>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold">Catalogos operativos</span>
                            <span class="badge text-bg-success">Activo</span>
                        </div>
                        <small class="text-secondary-emphasis">Servicios, operadores, tipos de operador y snapshots base ya aterrizados.</small>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold">Sucursales</span>
                            <span class="badge text-bg-success">Activo</span>
                        </div>
                        <small class="text-secondary-emphasis">CRUD, direccion atomizada, geocodificacion y base multisucursal listas.</small>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold">Agenda y resources</span>
                            <span class="badge text-bg-warning">Siguiente bloque</span>
                        </div>
                        <small class="text-secondary-emphasis">Pendiente aterrizar disponibilidad, reservas, bloqueos y activos compartidos.</small>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold">Documentacion viva</span>
                            <span class="badge text-bg-dark">Al dia</span>
                        </div>
                        <small class="text-secondary-emphasis">Bitacora con fecha diaria, checklist fechada y backlog de resources canonico.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="landing-glow landing-glow-one"></div>
    <div class="landing-glow landing-glow-two"></div>
</section>

<section class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6">
        <a href="{{ route('clients.index') }}" class="quick-card quick-card-primary text-decoration-none h-100 d-block rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="quick-card-kicker">Operacion</span>
                <span class="badge rounded-pill text-bg-light">Directo</span>
            </div>
            <h2 class="h4 mb-2">Clientes</h2>
            <p class="mb-0 text-white-50">Consulta, busca, edita y entra al flujo centrado en mascota desde un solo listado.</p>
        </a>
    </div>

    <div class="col-lg-3 col-md-6">
        <a href="{{ route('services.index') }}" class="quick-card quick-card-light text-decoration-none h-100 d-block rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="quick-card-kicker">Catalogo</span>
                <span class="badge rounded-pill text-bg-dark">Base</span>
            </div>
            <h2 class="h4 mb-2">Servicios</h2>
            <p class="mb-0 text-body-secondary">Catalogo comercial con snapshots historicos preparados para agenda y ejecucion.</p>
        </a>
    </div>

    <div class="col-lg-3 col-md-6">
        <a href="{{ route('operators.index') }}" class="quick-card quick-card-light text-decoration-none h-100 d-block rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="quick-card-kicker">Equipo</span>
                <span class="badge rounded-pill text-bg-dark">Activo</span>
            </div>
            <h2 class="h4 mb-2">Operadores</h2>
            <p class="mb-0 text-body-secondary">Roles multiples, foto de perfil, base operativa y ficha laboral ya integrados.</p>
        </a>
    </div>

    <div class="col-lg-3 col-md-6">
        <a href="{{ route('branches.index') }}" class="quick-card quick-card-muted text-decoration-none h-100 d-block rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="quick-card-kicker">Multisucursal</span>
                <span class="badge rounded-pill text-bg-success">Listo</span>
            </div>
            <h2 class="h4 mb-2">Sucursales</h2>
            <p class="mb-0 text-body-secondary">Direccion atomizada, coordenadas y preparacion clara para disponibilidad por sede.</p>
        </a>
    </div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 p-lg-5">
                <span class="section-kicker">Proximo bloque</span>
                <h2 class="h3 mt-2 mb-3">Resources ya tiene una ruta clara para no construir en falso.</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mini-block rounded-4 p-3 h-100">
                            <strong class="d-block mb-2">Fase 0</strong>
                            <small class="text-body-secondary">Definir alcance MVP, tipos base, estados y reglas de capacidad por recurso.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mini-block rounded-4 p-3 h-100">
                            <strong class="d-block mb-2">Fase 1</strong>
                            <small class="text-body-secondary">Crear `resources`, relaciones y CRUD apoyado en `branches` ya operativo.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mini-block rounded-4 p-3 h-100">
                            <strong class="d-block mb-2">Fase 2</strong>
                            <small class="text-body-secondary">Abrir disponibilidad real con `resource_allocations`, hotel y stays.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mini-block rounded-4 p-3 h-100">
                            <strong class="d-block mb-2">Fase 3+</strong>
                            <small class="text-body-secondary">Mantenimiento, auditoria, reportes y endurecimiento operativo.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 p-lg-5">
                <span class="section-kicker">Disciplina operativa</span>
                <h2 class="h3 mt-2 mb-3">La portada ya no es decorativa; resume criterio, estado y siguiente paso.</h2>
                <div class="d-grid gap-3">
                    <div class="mini-block rounded-4 p-3">
                        <strong class="d-block mb-1">Navegacion unica</strong>
                        <small class="text-body-secondary">Menu superior comun para escritorio y patron movil estable con `details/summary`.</small>
                    </div>
                    <div class="mini-block rounded-4 p-3">
                        <strong class="d-block mb-1">Backlog visible</strong>
                        <small class="text-body-secondary">Lo activo y lo siguiente se distinguen sin mezclar modulos cerrados con modulos futuros.</small>
                    </div>
                    <div class="mini-block rounded-4 p-3">
                        <strong class="d-block mb-1">Bitacora viva</strong>
                        <small class="text-body-secondary">Los cambios significativos y las checklists fechadas quedan alineados con la ejecucion real.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 p-lg-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-5">
                <span class="section-kicker">Accesos de arranque</span>
                <h2 class="h3 mt-2">Entradas rapidas para abrir trabajo sin cruzar menus de mas.</h2>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <div class="col-md-4">
                        <a href="{{ route('clients.create') }}" class="mini-block rounded-4 p-3 h-100 d-block text-decoration-none text-reset">
                            <strong class="d-block mb-2">Nuevo cliente</strong>
                            <small class="text-body-secondary">Alta rapida con direcciones, telefonos y mascotas en el mismo flujo.</small>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('operators.create') }}" class="mini-block rounded-4 p-3 h-100 d-block text-decoration-none text-reset">
                            <strong class="d-block mb-2">Nuevo operador</strong>
                            <small class="text-body-secondary">Alta de personal operativo con sucursal base y especialidades controladas.</small>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('branches.create') }}" class="mini-block rounded-4 p-3 h-100 d-block text-decoration-none text-reset">
                            <strong class="d-block mb-2">Nueva sucursal</strong>
                            <small class="text-body-secondary">Alta controlada de sede antes de abrir mas asignaciones y disponibilidad.</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
