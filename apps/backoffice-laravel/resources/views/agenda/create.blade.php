@php
    $screenDebugId = 'AgNew';
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')
@php
    $suggested = now()->addHour()->second(0);
    $remainder = $suggested->minute % 5;
    if ($remainder !== 0) {
        $suggested->addMinutes(5 - $remainder);
    }
    // Si "ahora + 1h" cae fuera del horario operativo, proponer el próximo bloque válido
    // en vez de una hora que el propio formulario va a rechazar al guardar.
    if ($suggested->format('H:i') < $openingTime) {
        $suggested->setTimeFromTimeString($openingTime);
    } elseif ($suggested->format('H:i') > $closingTime) {
        $suggested->addDay()->setTimeFromTimeString($openingTime);
    }
    $defaultScheduledAt = old('scheduled_at', $suggested->format('Y-m-d\TH:i'));
@endphp

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('agenda.index') }}" class="btn btn-outline-dark">Abrir agenda</a>
        <a href="{{ $isRootView ? route('pets.show', ['pet' => $pet, 'view' => $returnViewMode]) : route('clients.pets.show', [$client, $pet]) }}" class="btn btn-outline-secondary">Volver a mascota</a>
    </x-slot:actions>
</x-page-header>

{{-- SYNC-099: aviso persistente de rechazo del guardado. Antes, un error de negocio de
     storeForPet() (operador no calificado, fuera de horario, choque de agenda…) solo salía
     como toast de 5 s dependiente de JS — invisible en la práctica en esta pantalla pesada. --}}
@if(session('error') || session('warning') || $errors->any())
    <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            @if(session('error'))<div class="fw-semibold">{{ session('error') }}</div>@endif
            @if(session('warning'))<div>{{ session('warning') }}</div>@endif
            @if($errors->any())
                <div class="fw-semibold">Revisa los datos capturados:</div>
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            @endif
        </div>
    </div>
@endif

<div class="catalog-content-wide">
    <section class="catalog-overview mb-4">
        <div class="catalog-overview__grid">
            <article class="catalog-overview-card catalog-overview-card--primary">
                <span class="catalog-overview-card__eyebrow">Mascota objetivo</span>
                <div class="catalog-overview-card__value-sm">{{ $pet->name }}</div>
                <p class="catalog-overview-card__text">{{ $pet->species_label ?: 'Perfil sin especie' }} @if($pet->breed) · {{ $pet->breed }} @endif</p>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Cliente</span>
                <div class="catalog-overview-card__value-sm">{{ trim($client->first_name . ' ' . $client->last_name) }}</div>
                <div class="catalog-overview-card__label">contexto raíz de la programación</div>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Próximas sesiones</span>
                <div class="catalog-overview-card__value-sm">{{ $upcomingBookings->count() }}</div>
                <div class="catalog-overview-card__label">programadas para esta mascota</div>
            </article>
        </div>
    </section>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                <div>
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Programación</div>
                    <h2 class="h4 mb-2">Nueva sesión</h2>
                    <p class="text-body-secondary mb-0">La programación congela precio sugerido y mantiene contexto de mascota, cliente y catálogo base sin saltar a una pantalla aislada.</p>
                </div>
                <span class="badge rounded-pill text-bg-light border">Módulo inicial</span>
            </div>

            <form id="agenda-create-form" action="{{ $isRootView ? route('pets.bookings.store', $pet) : route('clients.pets.bookings.store', [$client, $pet]) }}" method="POST">
                @csrf
                @if($isRootView)
                    <input type="hidden" name="return_view_mode" value="{{ $returnViewMode }}">
                @endif

                {{-- El operador se elige por servicio (más abajo). El "responsable" de la cita
                     es el del primer servicio marcado — este hidden lo mantiene sincronizado el JS. --}}
                <input type="hidden" id="operator_id" name="operator_id" value="{{ old('operator_id') }}">

                <div class="catalog-filter-note mb-4">
                    <span class="catalog-filter-note__kicker">1 · Selección de servicios</span>
                    <p class="catalog-filter-note__text mb-0">Elige uno o más servicios activos y su operador. El precio se sugiere del catálogo, pero puedes editarlo antes de guardar.</p>
                </div>

                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3 mb-4">
                    @foreach($services as $service)
                        @php($checked = in_array($service->id, old('services', []), false))
                        @php($defaultPrice = (float) ($service->suggested_price ?? $service->price ?? 0))
                        <div class="col">
                            <label class="card h-100 shadow-sm service-card-label" style="cursor: pointer; transition: all 0.2s; border: 2px solid transparent;">
                                <div class="card-body position-relative p-3 d-flex flex-column">
                                    <div class="form-check position-absolute top-0 end-0 mt-3 me-3">
                                        <input class="form-check-input service-card-input" type="checkbox" name="services[]" value="{{ $service->id }}" @checked($checked) style="width: 1.25em; height: 1.25em;">
                                    </div>
                                    <div class="d-flex justify-content-between align-items-start mb-2 pe-4">
                                        <div>
                                            <div class="catalog-code-pill mb-2">{{ $service->code }}</div>
                                            <div class="fw-bold text-dark lh-sm" style="font-size: 0.95rem;">{{ $service->name }}</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <span class="catalog-type-pill">{{ strtoupper($service->type) }}</span>
                                    </div>
                                    {{-- SYNC-103: `operator_role_id` se eliminó — la elegibilidad real es
                                         plantilla de rol ∪ capacidad directa, u "abierto a todos" (SYNC-073). --}}
                                    @php($serviceRoleNames = $service->roleTemplates->pluck('role.name')->filter()->unique()->values())
                                    <div class="d-flex flex-column gap-1 mb-3">
                                        <span class="catalog-inline-tag text-truncate" title="{{ $service->open_to_all_operators ? 'Lo puede realizar cualquier operador activo' : ($serviceRoleNames->isNotEmpty() ? 'Requiere el rol: '.$serviceRoleNames->join(', ') : 'Requiere una capacidad asignada directamente al operador') }}">
                                            <i class="bi bi-person me-1"></i>
                                            {{ $service->open_to_all_operators ? 'Cualquier operador' : ($serviceRoleNames->isNotEmpty() ? $serviceRoleNames->join(', ') : 'Asignación directa') }}
                                        </span>
                                    </div>
                                    <div class="mt-auto border-top pt-2 d-flex flex-column gap-2">
                                        <div>
                                            <label class="catalog-stat__hint text-muted d-block" style="font-size: 0.7rem;">Precio</label>
                                            <div class="input-group input-group-sm service-price-group">
                                                <span class="input-group-text">$</span>
                                                <input
                                                    type="number"
                                                    name="service_prices[{{ $service->id }}]"
                                                    class="form-control service-price-input"
                                                    step="0.01"
                                                    min="0"
                                                    value="{{ old('service_prices.'.$service->id, $defaultPrice) }}"
                                                >
                                            </div>
                                        </div>
                                        <div>
                                            <label class="catalog-stat__hint text-muted d-block" style="font-size: 0.7rem;">Duración (min)</label>
                                            <input
                                                type="number"
                                                name="service_durations[{{ $service->id }}]"
                                                class="form-control form-control-sm service-config-input"
                                                step="5"
                                                min="5"
                                                max="480"
                                                value="{{ old('service_durations.'.$service->id, $service->suggested_duration_minutes ?: 30) }}"
                                                @disabled(! $checked)
                                            >
                                        </div>
                                        <div>
                                            <label class="catalog-stat__hint text-muted d-block" style="font-size: 0.7rem;">Operador</label>
                                            <select
                                                name="service_operators[{{ $service->id }}]"
                                                class="form-select form-select-sm service-config-input service-operator-select"
                                                @disabled(! $checked)
                                            >
                                                <option value="">Selecciona…</option>
                                                <option value="pending" @selected(old('service_operators.'.$service->id) === 'pending')>— Por asignar (lo resuelve piso) —</option>
                                                {{-- SYNC-101: solo operadores que de verdad pueden hacer este servicio
                                                     (OperatorServiceResolver::operatorsFor, SYNC-073) --}}
                                                @foreach($eligibleOperatorsByService[$service->id] as $operator)
                                                    <option value="{{ $operator->id }}" @selected((string) old('service_operators.'.$service->id) === (string) $operator->id)>{{ $operator->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="catalog-filter-note mb-4">
                    <span class="catalog-filter-note__kicker">2 · Fecha, jaula y notas</span>
                    <p class="catalog-filter-note__text mb-0">Con el operador ya elegido arriba, define cuándo. La barra valida su disponibilidad ese día.</p>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-6 col-md-6">
                        <label for="scheduled_at" class="form-label">Fecha y hora</label>
                        <div id="scheduled_at_wrapper" class="{{ old('operator_id') ? '' : 'is-locked' }}" style="position:relative;">
                            <input
                                id="scheduled_at"
                                type="datetime-local"
                                name="scheduled_at"
                                value="{{ $defaultScheduledAt }}"
                                class="form-control"
                                required
                                data-min-time="{{ $openingTime }}"
                                data-max-time="{{ $closingTime }}"
                            >
                        </div>
                        <div id="scheduled_at_hint" class="form-text">Horario operativo: {{ \Illuminate\Support\Carbon::parse($openingTime)->format($timeFormat) }}–{{ \Illuminate\Support\Carbon::parse($closingTime)->format($timeFormat) }}.</div>
                        <div id="availability_warning" class="form-text text-danger d-none"></div>
                        <div id="override_availability_wrapper" class="form-check mt-1 d-none">
                            <input type="checkbox" class="form-check-input" id="override_availability_checkbox" name="override_availability" value="1">
                            <label class="form-check-label small" for="override_availability_checkbox">Agendar de todas formas, fuera del horario del operador</label>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label for="resource_id" class="form-label">Jaula / recurso físico</label>
                        <select id="resource_id" name="resource_id" class="form-select">
                            <option value="">Sin asignar por ahora</option>
                            @foreach($resources as $resource)
                                <option value="{{ $resource->id }}" @selected((string) old('resource_id') === (string) $resource->id)>
                                    {{ $resource->code }} · {{ $resource->name }} · {{ $resource->branch?->name }}
                                </option>
                            @endforeach
                        </select>
                        {{-- La estancia en la jaula es independiente del servicio: entrada y salida
                             propias (el perro puede entrar antes, al terminar el baño, y salir horas
                             o días después). Los hidden se envían al servidor. --}}
                        <input type="hidden" name="resource_starts_at" id="resource_starts_at" value="{{ old('resource_starts_at') }}">
                        <input type="hidden" name="resource_ends_at" id="resource_ends_at" value="{{ old('resource_ends_at') }}">

                        <div id="resource_stay" class="mt-2 p-2 rounded border small" hidden>
                            <div class="fw-semibold mb-1">Estancia en la jaula</div>
                            <div class="d-flex flex-column gap-1">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <label for="resource_starts_at_input" class="mb-0 text-body-secondary" style="min-width:2.6rem;">Entra:</label>
                                    <input type="datetime-local" id="resource_starts_at_input" class="form-control form-control-sm" style="max-width: 15rem;">
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <label for="resource_ends_at_input" class="mb-0 text-body-secondary" style="min-width:2.6rem;">Sale:</label>
                                    <input type="datetime-local" id="resource_ends_at_input" class="form-control form-control-sm" style="max-width: 15rem;">
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap mt-1">
                                <button type="button" class="btn btn-link btn-sm p-0" data-stay-preset="service-start">Entra al iniciar el servicio</button>
                                <button type="button" class="btn btn-link btn-sm p-0" data-stay-preset="service-end">Entra al terminar el servicio</button>
                                <button type="button" class="btn btn-link btn-sm p-0" id="stay-snap">Ajustar al primer hueco libre de la jaula</button>
                            </div>
                            <div id="stay_status" class="mt-1 fw-semibold"></div>
                            <div class="form-text mt-1">La barra de la estancia se ve en el panel de disponibilidad, debajo de la del operador. Ajústala con los campos Entra/Sale o el botón.</div>
                        </div>

                        <div class="form-text">Si seleccionas jaula, la agenda bloqueará también {{ $resourceCleaningBufferMinutes }} min de limpieza al finalizar.</div>
                    </div>
                    <div class="col-lg-4 col-md-12">
                        <label for="notes" class="form-label">Notas operativas</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="Indicaciones relevantes para recepción, grooming o preparación.">{{ old('notes') }}</textarea>
                        <div class="form-text">Se guardan junto al booking para que no dependan de notas dispersas del cliente o de la mascota.</div>
                    </div>
                    <div class="col-12">
                        <div id="availability_panel" class="alert d-none mb-0 py-2 px-3 small"></div>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <a href="{{ $isRootView ? route('pets.show', ['pet' => $pet, 'view' => $returnViewMode]) : route('clients.pets.show', [$client, $pet]) }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" id="agenda-create-submit" class="btn btn-primary">Guardar programación</button>
                </div>
            </form>
        </div>
    </div>

    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-1">Próximas sesiones de esta mascota</h2>
                <p class="text-muted mb-0">Referencia rápida para no sobreprogramar ni perder continuidad operativa.</p>
            </div>
        </div>

        <x-list-table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Servicios</th>
                    <th>Estado</th>
                    <th>Total estimado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($upcomingBookings as $booking)
                    <tr style="cursor: pointer;" onclick="window.location='{{ route('agenda.show', $booking) }}'" class="hover-row">
                        <td>
                            <div class="catalog-stat">{{ $booking->scheduled_at?->format($datetimeFormat) }}</div>
                            <div class="catalog-stat__hint">{{ $booking->scheduled_at?->diffForHumans() }}</div>
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
                            <div class="catalog-stat">${{ number_format((float) $booking->total_estimated_price, 2) }}</div>
                        </td>
                    </tr>
@push('styles')
<style>
    .hover-row:hover {
        background-color: rgba(0,0,0,0.02);
    }
</style>
@endpush
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-body-secondary">Todavía no hay sesiones futuras para esta mascota.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-list-table>
    </section>
</div>

@push('styles')
<style>
    .service-card-label:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important;
    }
    .service-card-label:has(.service-card-input:checked) {
        background-color: var(--bs-primary-bg-subtle) !important;
        border-color: var(--bs-primary) !important;
    }
    .hover-row:hover {
        background-color: rgba(0,0,0,0.02);
    }
</style>
@endpush

@push('styles')
<style>
    #scheduled_at_wrapper.is-locked {
        opacity: .5;
        pointer-events: none;
    }
    /* Barra de la agenda del día del operador en el panel de disponibilidad */
    .agenda-daybar {
        position: relative;
        height: 28px;
        border-radius: 4px;
        background: repeating-linear-gradient(90deg, rgba(25,135,84,.10) 0 8px, rgba(25,135,84,.16) 8px 16px);
        border: 1px solid rgba(0,0,0,.1);
        overflow: hidden;
    }
    /* Marca de cada hora en punto dentro de la barra */
    .agenda-daybar-hour {
        position: absolute;
        top: 0; bottom: 0;
        width: 1px;
        background: rgba(0,0,0,.16);
    }
    .agenda-slot--busy {
        position: absolute;
        top: 0; bottom: 0;
        background: rgba(220,53,69,.55);
        border-left: 1px solid rgba(220,53,69,.9);
        border-right: 1px solid rgba(220,53,69,.9);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .58rem;
        font-weight: 700;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
    }
    /* Bloque de la cita que se está insertando — se puede arrastrar sobre la barra */
    .agenda-daybar-sel {
        position: absolute;
        top: 1px; bottom: 1px;
        min-width: 3px;
        background: rgba(13, 110, 253, .22);
        border: 1px solid #0d6efd;
        border-left-width: 3px;
        border-radius: 2px;
        cursor: grab;
        touch-action: none;
        z-index: 3;
    }
    .agenda-daybar-sel:active,
    .agenda-daybar-sel.is-dragging { cursor: grabbing; }
    .agenda-daybar-sel.is-clash {
        background: rgba(220, 53, 69, .22);
        border-color: #dc3545;
    }
    /* Fila de la estancia en la jaula, alineada bajo la barra del operador */
    .agenda-stay-label {
        font-size: .68rem;
        margin-top: .5rem;
        margin-bottom: .15rem;
    }
    .agenda-stay-bar {
        background: repeating-linear-gradient(90deg, rgba(108,117,125,.08) 0 8px, rgba(108,117,125,.14) 8px 16px);
    }
    .agenda-stay-bar .agenda-daybar-sel {
        background: rgba(111, 66, 193, .20);
        border-color: #6f42c1;
    }
    .agenda-stay-bar .agenda-daybar-sel.is-clash {
        background: rgba(220, 53, 69, .22);
        border-color: #dc3545;
    }
    /* La barra de la estancia es solo lectura — el bloque no invita a arrastrar. */
    .agenda-daybar-sel.is-readonly {
        cursor: default;
        touch-action: auto;
        pointer-events: none;
    }
    /* Sombra del servicio como referencia dentro de la barra de la estancia. */
    .agenda-daybar-ghost {
        position: absolute;
        top: 2px; bottom: 2px;
        background: repeating-linear-gradient(45deg, rgba(0,0,0,.06) 0 5px, rgba(0,0,0,.14) 5px 10px);
        border: 1px dashed rgba(0,0,0,.35);
        border-radius: 2px;
        z-index: 2;
        pointer-events: none;
    }
    .agenda-daybar-draghint {
        font-size: .62rem;
        opacity: .7;
        margin-top: .1rem;
    }
    /* Leyenda de colores de la barra */
    .agenda-daybar-legend {
        display: flex;
        flex-wrap: wrap;
        gap: .1rem .8rem;
        font-size: .64rem;
        opacity: .9;
        margin: .4rem 0 .2rem;
    }
    .agenda-daybar-legend span { display: inline-flex; align-items: center; gap: .3rem; }
    .agenda-daybar-legend i { width: .8rem; height: .8rem; border-radius: 2px; display: inline-block; }
    .agenda-daybar-legend i.is-free { background: rgba(25,135,84,.22); border: 1px solid rgba(25,135,84,.5); }
    .agenda-daybar-legend i.is-busy { background: rgba(220,53,69,.55); border: 1px solid rgba(220,53,69,.9); }
    .agenda-daybar-legend i.is-pick { background: rgba(13, 110, 253, .22); border: 1px solid #0d6efd; }
    .agenda-daybar-legend i.is-stay { background: rgba(111, 66, 193, .20); border: 1px solid #6f42c1; }
    .agenda-daybar-legend i.is-svc {
        background: repeating-linear-gradient(45deg, rgba(0,0,0,.06) 0 3px, rgba(0,0,0,.14) 3px 6px);
        border: 1px dashed rgba(0,0,0,.35);
    }
    /* Eje de horas debajo de la barra */
    .agenda-daybar-axis {
        position: relative;
        height: 1rem;
        margin-top: 2px;
        font-size: .62rem;
        opacity: .8;
    }
    .agenda-daybar-axis span {
        position: absolute;
        transform: translateX(-50%);
        white-space: nowrap;
    }
    .agenda-daybar-axis span.is-start { transform: translateX(0); }
    .agenda-daybar-axis span.is-end { transform: translateX(-100%); }
    /* Hora elegida, en su propia línea bajo el eje, alineada al marcador azul */
    .agenda-daybar-pick {
        position: relative;
        height: .95rem;
    }
    .agenda-daybar-pick span {
        position: absolute;
        transform: translateX(-50%);
        font-size: .62rem;
        font-weight: 700;
        color: #0d6efd;
        white-space: nowrap;
    }
</style>
@endpush

@push('scripts')
<script nonce="{{ csp_nonce() }}">
    document.addEventListener('DOMContentLoaded', function () {
        var operatorSelect = document.getElementById('operator_id');
        var resourceSelect = document.getElementById('resource_id');
        var wrapper = document.getElementById('scheduled_at_wrapper');
        var scheduledAtInput = document.getElementById('scheduled_at');
        var scheduledAtHint = document.getElementById('scheduled_at_hint');
        var warningEl = document.getElementById('availability_warning');
        var overrideWrapper = document.getElementById('override_availability_wrapper');
        var overrideCheckbox = document.getElementById('override_availability_checkbox');
        if (!operatorSelect || !wrapper) return;

        var scheduleHintDefault = scheduledAtHint ? scheduledAtHint.textContent : '';

        function syncScheduledAtState() {
            var locked = !operatorSelect.value;
            wrapper.classList.toggle('is-locked', locked);
            if (scheduledAtHint) {
                scheduledAtHint.textContent = locked
                    ? 'Elige un operador arriba para habilitar la fecha y hora.'
                    : scheduleHintDefault;
                scheduledAtHint.classList.toggle('text-warning', locked);
            }
        }

        var availabilityPanel = document.getElementById('availability_panel');
        var BIZ_OPEN = @json($openingTime);
        var BIZ_CLOSE = @json($closingTime);

        function esc(v) {
            var d = document.createElement('div');
            d.textContent = v == null ? '' : String(v);
            return d.innerHTML;
        }

        function toMin(hhmm) {
            var p = String(hhmm || '').split(':');
            return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
        }

        // El formato de hora lo manda el ajuste del sistema (Configuración › Formato de hora),
        // igual que el resto del backoffice. El backend siempre entrega "HH:MM" en 24h; acá se
        // convierte a "hh:mm AM/PM" cuando el sistema está en 12h, para no mezclar formatos.
        var USE_24H = document.body.dataset.time24h === '1';
        function fmtHM(hhmm) {
            var p = String(hhmm || '').split(':');
            var h = parseInt(p[0], 10);
            if (isNaN(h)) return hhmm || '';
            var m = p[1] != null ? String(p[1]) : '00';
            if (m.length < 2) m = ('0' + m).slice(-2);
            if (USE_24H) return (h < 10 ? '0' : '') + h + ':' + m;
            var h12 = h % 12; if (h12 === 0) h12 = 12;
            return (h12 < 10 ? '0' : '') + h12 + ':' + m + ' ' + (h < 12 ? 'AM' : 'PM');
        }
        function fmtHour(h) {
            if (USE_24H) return h + ':00';
            var h12 = h % 12; if (h12 === 0) h12 = 12;
            return h12 + ' ' + (h < 12 ? 'AM' : 'PM');
        }

        function minToHHMM(min) {
            min = Math.max(0, Math.round(min));
            var h = Math.floor(min / 60), m = min % 60;
            return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
        }

        // Duración total de la cita = suma de las duraciones de los servicios marcados
        // (o 30 min si aún no hay ninguno), para dibujar el bloque a escala.
        function selectedDurationMinutes() {
            var total = 0;
            document.querySelectorAll('.service-card-label').forEach(function (card) {
                var cb = card.querySelector('.service-card-input');
                if (!cb || !cb.checked) return;
                var d = card.querySelector('input[name^="service_durations"]');
                var v = d ? parseInt(d.value, 10) : 0;
                if (!isNaN(v) && v > 0) total += v;
            });
            return total > 0 ? total : 30;
        }

        function overlapsBusy(startM, dur, busy) {
            for (var i = 0; i < busy.length; i++) {
                if (startM < busy[i][1] && startM + dur > busy[i][0]) return true;
            }
            return false;
        }

        // Ajusta un inicio deseado al hueco libre más cercano donde la cita quepa entera
        // (dentro de la ventana del operador y sin pisar lo ocupado). Si nada cabe, deja
        // el inicio pegado a la ventana y el bloque se muestra en rojo.
        function snapToFit(desired, w0, w1, dur, busy) {
            desired = Math.round(desired / 5) * 5;
            desired = Math.max(w0, Math.min(w1 - dur, desired));
            if (!overlapsBusy(desired, dur, busy)) return desired;
            var b = busy.slice().sort(function (a, c) { return a[0] - c[0]; });
            var gaps = [], cursor = w0;
            for (var i = 0; i < b.length; i++) {
                if (b[i][0] > cursor) gaps.push([cursor, b[i][0]]);
                cursor = Math.max(cursor, b[i][1]);
            }
            if (cursor < w1) gaps.push([cursor, w1]);
            var best = null, bestDist = Infinity;
            gaps.forEach(function (g) {
                if (g[1] - g[0] < dur) return;
                var cand = Math.max(g[0], Math.min(g[1] - dur, desired));
                var dist = Math.abs(cand - desired);
                if (dist < bestDist) { bestDist = dist; best = cand; }
            });
            return best != null ? best : desired;
        }

        // Escribe una hora (minutos desde 00:00) en el campo, conservando el día ya elegido,
        // y vuelve a pedir disponibilidad.
        function commitSelectedMinute(min) {
            var fp = scheduledAtInput && scheduledAtInput._flatpickr;
            var base = (fp && fp.selectedDates && fp.selectedDates[0])
                ? new Date(fp.selectedDates[0])
                : new Date(scheduledAtInput.value || Date.now());
            if (isNaN(base.getTime())) base = new Date();
            base.setHours(Math.floor(min / 60), Math.round(min % 60), 0, 0);
            if (fp) {
                fp.setDate(base, false);
            } else {
                var pad = function (n) { return (n < 10 ? '0' : '') + n; };
                scheduledAtInput.value = base.getFullYear() + '-' + pad(base.getMonth() + 1) + '-' + pad(base.getDate()) +
                    'T' + pad(base.getHours()) + ':' + pad(base.getMinutes());
            }
            checkAvailability();
        }

        function currentSelectedHHMM() { return (scheduledAtInput.value || '').slice(11, 16); }
        function countCheckedServices() {
            var n = 0;
            document.querySelectorAll('.service-card-input').forEach(function (cb) { if (cb.checked) n++; });
            return n;
        }
        function setSingleServiceDuration(mins) {
            mins = Math.max(5, Math.min(480, Math.round(mins / 5) * 5));
            document.querySelectorAll('.service-card-label').forEach(function (card) {
                var cb = card.querySelector('.service-card-input');
                if (!cb || !cb.checked) return;
                var d = card.querySelector('input[name^="service_durations"]');
                if (d) d.value = mins;
            });
        }

        // ── Estancia en la jaula ─────────────────────────────────────────────────────────
        // Independiente del servicio: entrada y salida propias. El perro puede entrar antes,
        // al terminar el baño, y salir horas o días después.
        var resourceStayBox = document.getElementById('resource_stay');
        var resourceStartsInput = document.getElementById('resource_starts_at_input');
        var resourceEndsInput = document.getElementById('resource_ends_at_input');
        var stayStatusEl = document.getElementById('stay_status');
        var resStartHidden = document.getElementById('resource_starts_at');
        var resEndHidden = document.getElementById('resource_ends_at');
        var stayStartTouched = false, stayEndTouched = false;
        var lastResourceData = null;

        function pad2(n) { return (n < 10 ? '0' : '') + n; }
        function toLocalInput(d) {
            return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) +
                'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
        }
        function toSpace(v) { return String(v || '').replace('T', ' ').slice(0, 16); }
        function fmtDT(d) {
            return pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1) + ' · ' +
                fmtHM(pad2(d.getHours()) + ':' + pad2(d.getMinutes()));
        }
        function serviceStartDate() {
            var d = new Date(scheduledAtInput.value || Date.now());
            return isNaN(d.getTime()) ? new Date() : d;
        }
        function serviceEndDate() {
            return new Date(serviceStartDate().getTime() + selectedDurationMinutes() * 60000);
        }
        function fpDate(inp, fallback) {
            var fp = inp && inp._flatpickr;
            var d = (fp && fp.selectedDates && fp.selectedDates[0])
                ? new Date(fp.selectedDates[0])
                : new Date(inp && inp.value || NaN);
            return isNaN(d.getTime()) ? fallback : d;
        }
        function stayStartD() { return fpDate(resourceStartsInput, serviceStartDate()); }
        function stayEndD() { return fpDate(resourceEndsInput, serviceEndDate()); }
        function setFp(inp, d) {
            var fp = inp && inp._flatpickr;
            if (fp) fp.setDate(d, false);
            else if (inp) inp.value = toLocalInput(d);
        }
        function sameDay(a, b) { return a.toDateString() === b.toDateString(); }
        function minOfDay(d) { return d.getHours() * 60 + d.getMinutes(); }

        var syncingStay = false;
        function syncStay() {
            var on = !!(resourceSelect && resourceSelect.value && scheduledAtInput.value);
            if (resourceStayBox) resourceStayBox.hidden = !on;
            if (!on) {
                if (resStartHidden) resStartHidden.value = '';
                if (resEndHidden) resEndHidden.value = '';
                return;
            }
            if (syncingStay) return;   // re-entrada (un setFp disparó un 'change') → no recursar
            syncingStay = true;
            try {
                // Por defecto la estancia arranca cuando TERMINA el servicio (el perro se guarda
                // después del baño/corte) y dura 1 h; el usuario la mueve/estira si hace falta.
                if (!stayStartTouched) setFp(resourceStartsInput, serviceEndDate());
                if (!stayEndTouched) setFp(resourceEndsInput, new Date(serviceEndDate().getTime() + 60 * 60000));
                // La salida no puede caer antes o igual que la entrada.
                if (stayEndD() <= stayStartD()) setFp(resourceEndsInput, new Date(stayStartD().getTime() + 30 * 60000));
                var fpe = resourceEndsInput && resourceEndsInput._flatpickr;
                if (fpe) fpe.set('minDate', stayStartD());
            } finally {
                syncingStay = false;
            }
            if (resStartHidden) resStartHidden.value = toSpace(toLocalInput(stayStartD()));
            if (resEndHidden) resEndHidden.value = toSpace(toLocalInput(stayEndD()));
        }

        // Barra de la estancia en la jaula — va DENTRO del panel de disponibilidad, JUSTO DEBAJO
        // de la barra del operador y con la MISMA escala [w0,w1] (así quedan alineadas y se ve la
        // estancia contra la hora del servicio). Se arma como HTML aquí; `renderAvailability` la
        // cablea con `wireStayBlock` (arrastrar/redimensionar), igual que la del operador con
        // `wireOperatorBlock` — misma máquina, `attachBlockDrag`. La primera versión de esto
        // (antes de `SYNC-086`) se intentó arrastrable y causó doble/triple asignación de jaula;
        // ese bug venía de que esa versión escribía la reserva en cada paso del arrastre — la
        // máquina actual (`attachBlockDrag`) solo toca los campos Entra/Sale en el cliente y
        // dispara `checkAvailability()` (lectura), la reserva real solo se crea al enviar el
        // formulario (con el candado anti doble-submit de `storeForPet`), así que no aplica.
        // **Excepción:** una estancia que cruza medianoche (`multiDay`) se queda de solo lectura
        // — el rango de arrastre [w0,w1] es en minutos-de-un-día, no representa bien un tramo de
        // varios días; para eso se sigue usando Entra/Sale a mano.
        //   🟥 jaula ocupada (intervalos ya fusionados por el backend) · 🟪 morado = esta estancia
        //   · gris rayado = el servicio.
        function stayBarRow(w0, w1) {
            if (!(resourceSelect && resourceSelect.value && scheduledAtInput.value)) return '';
            var span = w1 - w0;
            if (!(span > 0)) return '';

            var s = stayStartD(), e = stayEndD();
            var multiDay = !sameDay(s, e);
            var sm = minOfDay(s);
            var em = multiDay ? w1 : minOfDay(e);   // si cruce de días, la barra llega al borde
            var svc = sameDay(s, serviceStartDate())
                ? [minOfDay(serviceStartDate()), minOfDay(serviceEndDate())] : null;
            var res = lastResourceData || {};

            var p = function (m) { return Math.max(0, Math.min(100, ((m - w0) / span) * 100)); };

            // `day_busy` = ocupación del DÍA COMPLETO de la jaula (mismo criterio que la barra
            // del operador), no solo lo que choca con la ventana de estancia propuesta — si no,
            // la barra casi nunca muestra nada real (solo por casualidad si el horario por
            // defecto ya caía encima de una reserva existente).
            var busyBlocks = (res.day_busy || []).map(function (b) {
                var l = p(toMin(b.start)), r = p(toMin(b.end));
                if (r <= l) return '';
                return '<div class="agenda-slot agenda-slot--busy" style="left:' + l + '%;width:' + (r - l) + '%" title="Jaula ocupada ' + esc(b.text || '') + '"></div>';
            }).join('');
            var ghost = '';
            if (svc && svc[1] > svc[0]) {
                var gl = p(svc[0]);
                ghost = '<div class="agenda-daybar-ghost" style="left:' + gl + '%;width:' + Math.max(0.6, p(svc[1]) - gl) + '%" title="Servicio"></div>';
            }
            var sl = p(sm), sw = Math.max(0.8, p(em) - sl);
            // `available` sale del backend con la MISMA ventana [s,e] que se está dibujando
            // aquí (se recalcula server-side en cada `checkAvailability`) — más confiable que
            // recomputar el solape en el cliente con datos que podrían ir un paso atrás.
            var clash = res.available === false;
            var titleTxt = multiDay
                ? 'Estancia ' + fmtDT(s) + ' → ' + fmtDT(e)
                : 'Estancia ' + fmtHM(minToHHMM(sm)) + '–' + fmtHM(minToHHMM(minOfDay(e)));
            // Interactivo (arrastrar/redimensionar) salvo cruce de medianoche — ver comentario
            // arriba de `stayBarRow`. `data-w0/-w1/-busy` los lee `wireStayBlock` después de
            // insertar este HTML en el DOM (mismo patrón que el bloque del operador).
            var interactive = !multiDay;
            var dayBusyMins = (res.day_busy || []).map(function (b) { return [toMin(b.start), toMin(b.end)]; });
            var stayBlock = '<div class="agenda-daybar-sel' + (interactive ? '' : ' is-readonly') + (clash ? ' is-clash' : '') + '"' +
                ' style="left:' + sl + '%;width:' + sw + '%"' +
                (interactive ? ' data-w0="' + w0 + '" data-w1="' + w1 + '"' + " data-busy='" + JSON.stringify(dayBusyMins) + "'" : '') +
                ' title="' + esc(titleTxt) + '"></div>';

            var label = 'Estancia en la jaula' + (res.name ? ' · ' + esc(res.name) : '') +
                (multiDay ? ' · <span class="text-body-secondary">' + esc(fmtDT(s)) + ' → ' + esc(fmtDT(e)) + '</span>' : '');

            return '<div class="agenda-stay-label">' + label + '</div>' +
                '<div class="agenda-daybar agenda-stay-bar">' + busyBlocks + ghost + stayBlock + '</div>' +
                '<div class="agenda-daybar-legend">' +
                    '<span><i class="is-busy"></i>Jaula ocupada</span>' +
                    '<span><i class="is-stay"></i>Esta estancia</span>' +
                    (svc ? '<span><i class="is-svc"></i>Servicio</span>' : '') +
                '</div>' +
                (interactive ? '<div class="agenda-daybar-draghint">Arrastra o toma un borde para ajustar Entra/Sale.</div>' : '');
        }

        function renderStayStatus(res) {
            lastResourceData = res || null;
            if (!stayStatusEl) return;
            if (!res) {
                stayStatusEl.textContent = '';
            } else if (res.available !== false) {
                stayStatusEl.className = 'mt-1 fw-semibold text-success';
                stayStatusEl.textContent = '✓ Jaula libre en toda la estancia.';
            } else {
                var w = (res.busy || []).map(function (b) { return b.text; }).filter(Boolean).join(', ');
                stayStatusEl.className = 'mt-1 fw-semibold text-danger';
                stayStatusEl.textContent = '⚠ Jaula ocupada' + (w ? ': ' + w : '') + '.';
            }
        }

        [resourceStartsInput, resourceEndsInput].forEach(function (inp) {
            inp && inp.addEventListener('change', function () {
                if (syncingStay) return;   // cambio provocado por setFp, no por el usuario
                if (inp === resourceStartsInput) { stayStartTouched = true; } else { stayEndTouched = true; }
                syncStay();
                checkAvailability();
            });
        });
        document.querySelectorAll('[data-stay-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var d = btn.dataset.stayPreset === 'service-end' ? serviceEndDate() : serviceStartDate();
                setFp(resourceStartsInput, d);
                stayStartTouched = true;
                if (stayEndD() <= d) { setFp(resourceEndsInput, new Date(d.getTime() + 60 * 60000)); stayEndTouched = true; }
                syncStay();
                checkAvailability();
            });
        });

        // "Ajustar al primer hueco libre de la jaula": desde el fin del servicio, busca el
        // primer tramo libre de esa jaula (ese día, dentro del horario) que aguante la
        // estancia completa, y mueve Entra/Sale ahí.
        var staySnapBtn = document.getElementById('stay-snap');
        staySnapBtn && staySnapBtn.addEventListener('click', function () {
            var res = lastResourceData;
            if (!res) return;
            var day = serviceEndDate();
            var desired = minOfDay(day);
            var dur = Math.max(15, Math.round((stayEndD().getTime() - stayStartD().getTime()) / 60000));
            var w0 = Math.min(toMin(BIZ_OPEN), Math.floor(desired / 60) * 60);
            var w1 = Math.max(toMin(BIZ_CLOSE), Math.ceil((desired + dur) / 60) * 60);
            var busy = (res.busy || []).map(function (b) { return [toMin(b.start), toMin(b.end)]; });
            var fit = snapToFit(desired, Math.max(w0, desired), w1, dur, busy);
            var a = new Date(day); a.setHours(Math.floor(fit / 60), fit % 60, 0, 0);
            var b = new Date(day); b.setHours(Math.floor((fit + dur) / 60), (fit + dur) % 60, 0, 0);
            setFp(resourceStartsInput, a); setFp(resourceEndsInput, b);
            stayStartTouched = stayEndTouched = true;
            syncStay();
            checkAvailability();
        });

        // Cuántos bloques se están arrastrando ahora mismo (0 o 1 en la práctica, nunca dos a la
        // vez) — mientras sea > 0, `_checkAvailabilityRun()` no debe reemplazar el panel bajo el
        // cursor del usuario. Ver el comentario largo de `attachBlockDrag` (SYNC-095, portado
        // desde `edit.blade.php`).
        var dragCount = 0;

        // Interacción de un bloque sobre una barra: arrastrar el cuerpo para mover, o tomar
        // un borde (ver `edgeAt`/`MIN_MOVE_ZONE` abajo) para cambiar inicio/fin (duración).
        // Mouse y touch (pointer events).
        // cfg: { w0, w1, minDur, edges:'both'|'end'|'none', getRange:fn()->[st,en],
        //        onDraw:fn(st,en), onCommit:fn(st,en,mode) }
        //
        // SYNC-095 (encontrado en `AgSpaEdi`, portado aquí — mismo bug, mismo fix): dos causas
        // de que un bloque pareciera "redimensionarse solo" al arrastrarlo.
        // 1. `EDGE` fijo en 10px sin importar qué tan angosto sea el bloque ya dibujado — en una
        //    estancia corta (~20px de ancho ya dibujado) esos 10px por lado se comían casi todo
        //    el bloque, así que un intento de moverlo entero agarraba en realidad un borde y lo
        //    redimensionaba. Confirmado con arrastre real simulado (Playwright), no solo lectura
        //    de código: un primer intento (borde ≤ un tercio del ancho) seguía dejando muy poco
        //    margen central (~6px de 20px) — casi cualquier clic real seguía fallando. Ahora el
        //    centro para mover nunca es menor a 16px, sin importar qué tan angosto esté el bloque.
        // 2. Un `checkAvailability()` pendiente de antes del arrastre podía reemplazar todo el
        //    panel a mitad del gesto con la posición vieja — el bloque "saltaba" de vuelta.
        //    Ahora `_checkAvailabilityRun()` se reprograma solo mientras `dragCount > 0`.
        function attachBlockDrag(barEl, selEl, cfg) {
            if (!barEl || !selEl || cfg.w1 - cfg.w0 <= 0) return;
            // Un mismo bloque no se cablea dos veces (evita listeners duplicados → el bloque
            // "se atora" o salta con doble asignación al arrastrar).
            if (selEl.dataset.dragBound === '1') return;
            selEl.dataset.dragBound = '1';
            var span = cfg.w1 - cfg.w0, EDGE = 10;
            var mode = null, anchor = 0, cur = cfg.getRange();

            function minAt(cx) {
                var r = barEl.getBoundingClientRect();
                var ratio = r.width ? (cx - r.left) / r.width : 0;
                return cfg.w0 + Math.max(0, Math.min(1, ratio)) * span;
            }
            function edgeAt(cx) {
                var r = selEl.getBoundingClientRect();
                // MIN_MOVE_ZONE: verificado con arrastre real (Playwright) — "un tercio del
                // ancho" (SYNC-095, 3ª vuelta) seguía dejando muy poco margen central en un
                // bloque en `minDur` (~20px): casi cualquier clic real caía en zona de borde y
                // redimensionaba en vez de mover. El centro para mover nunca es menor a 16px.
                var edge = Math.max(0, Math.min(EDGE, (r.width - 16) / 2));
                if (cfg.edges === 'both' && cx - r.left <= edge) return 'start';
                if ((cfg.edges === 'both' || cfg.edges === 'end') && r.right - cx <= edge) return 'end';
                return 'move';
            }
            function draw(st, en) {
                st = Math.round(st / 5) * 5; en = Math.round(en / 5) * 5;
                if (en - st < cfg.minDur) { if (mode === 'start') st = en - cfg.minDur; else en = st + cfg.minDur; }
                st = Math.max(cfg.w0, st); en = Math.min(cfg.w1, en);
                if (en - st < cfg.minDur) return;
                cur = [st, en];
                selEl.style.left = ((st - cfg.w0) / span * 100) + '%';
                selEl.style.width = ((en - st) / span * 100) + '%';
                if (cfg.onDraw) cfg.onDraw(st, en);
            }
            selEl.addEventListener('pointermove', function (ev) {
                if (!mode) selEl.style.cursor = edgeAt(ev.clientX) === 'move' ? 'grab' : 'ew-resize';
            });
            function onMove(ev) {
                var m = minAt(ev.clientX);
                if (mode === 'start') draw(m, cur[1]);
                else if (mode === 'end') draw(cur[0], m);
                else {
                    var d = cur[1] - cur[0], st = m - anchor;
                    if (st < cfg.w0) st = cfg.w0;
                    if (st + d > cfg.w1) st = cfg.w1 - d;
                    draw(st, st + d);
                }
            }
            function onUp() {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
                selEl.classList.remove('is-dragging');
                dragCount = Math.max(0, dragCount - 1);
                cfg.onCommit(cur[0], cur[1], mode);
                mode = null;
            }
            selEl.addEventListener('pointerdown', function (ev) {
                ev.preventDefault();
                mode = edgeAt(ev.clientX);
                cur = cfg.getRange();
                anchor = minAt(ev.clientX) - cur[0];
                selEl.classList.add('is-dragging');
                dragCount++;
                document.addEventListener('pointermove', onMove);
                document.addEventListener('pointerup', onUp);
            });
        }

        function wireOperatorBlock(bar, sel, busy) {
            var w0 = +sel.dataset.w0, w1 = +sel.dataset.w1;
            if (isNaN(w0) || isNaN(w1)) return;
            var one = countCheckedServices() === 1;
            attachBlockDrag(bar, sel, {
                w0: w0, w1: w1, minDur: 5, edges: one ? 'both' : 'none',
                getRange: function () { var m = toMin(currentSelectedHHMM()); return [m, m + selectedDurationMinutes()]; },
                onDraw: function (st, en) {
                    sel.classList.toggle('is-clash', overlapsBusy(st, en - st, busy));
                    sel.title = fmtHM(minToHHMM(st)) + '–' + fmtHM(minToHHMM(en));
                },
                onCommit: function (st, en, mode) {
                    if (mode === 'move') {
                        commitSelectedMinute(snapToFit(st, w0, w1, en - st, busy));
                    } else {
                        setSingleServiceDuration(en - st);
                        commitSelectedMinute(st);
                    }
                },
            });
        }

        // Arrastrar/redimensionar la barra de la estancia en la jaula — misma máquina que
        // `wireOperatorBlock` (`attachBlockDrag`), pero escribe en Entra/Sale (`resourceStartsInput`/
        // `resourceEndsInput`) en vez del campo de hora de la cita. Solo se llama cuando `stayBarRow`
        // marcó el bloque como interactivo (no cruza medianoche) — ver comentario ahí.
        function wireStayBlock(bar, sel, busy) {
            var w0 = +sel.dataset.w0, w1 = +sel.dataset.w1;
            if (isNaN(w0) || isNaN(w1)) return;
            attachBlockDrag(bar, sel, {
                w0: w0, w1: w1, minDur: 15, edges: 'both',
                getRange: function () { return [minOfDay(stayStartD()), minOfDay(stayEndD())]; },
                onDraw: function (st, en) {
                    sel.classList.toggle('is-clash', overlapsBusy(st, en - st, busy));
                    sel.title = 'Estancia ' + fmtHM(minToHHMM(st)) + '–' + fmtHM(minToHHMM(en));
                },
                onCommit: function (st, en) {
                    var day = stayStartD();
                    var a = new Date(day); a.setHours(Math.floor(st / 60), st % 60, 0, 0);
                    var b = new Date(day); b.setHours(Math.floor(en / 60), en % 60, 0, 0);
                    setFp(resourceStartsInput, a);
                    // El campo "Sale" puede traer un `minDate` viejo — de la última vez que
                    // `syncStay()` lo puso, basado en el Entra ANTERIOR a este arrastre. Si el
                    // arrastre mueve la estancia completa a una hora más temprana, el nuevo
                    // "Sale" cae ANTES de ese `minDate` viejo — flatpickr rechaza la fecha en
                    // silencio y el campo queda vacío. `stayEndD()` entonces cae a su valor por
                    // defecto (`serviceEndDate()`), una ventana mucho más ancha que la real — se
                    // ve como "la jaula se redimensiona sola" al mover el bloque (encontrado y
                    // confirmado con capturas reales en `AgSpaEdi`, portado aquí — mismo bug).
                    // Se relaja el límite antes de fijar el valor nuevo; `syncStay()`, un par de
                    // líneas abajo, ya pone el `minDate` correcto para el próximo arrastre.
                    var fpe = resourceEndsInput && resourceEndsInput._flatpickr;
                    if (fpe) fpe.set('minDate', null);
                    setFp(resourceEndsInput, b);
                    stayStartTouched = stayEndTouched = true;
                    syncStay();
                    checkAvailability();
                },
            });
        }

        function dayTimeline(s, selectedHHMM, durMin) {
            var hasWindow = !!(s.window && s.window.start && s.window.end);
            var startM = hasWindow ? toMin(s.window.start) : toMin(BIZ_OPEN);
            var endM = hasWindow ? toMin(s.window.end) : toMin(BIZ_CLOSE);
            if (endM <= startM) return '';
            var total = endM - startM;
            var pct = function (m) { return Math.max(0, Math.min(100, ((m - startM) / total) * 100)); };
            var startLabel = fmtHM(hasWindow ? s.window.start : BIZ_OPEN);
            var endLabel = fmtHM(hasWindow ? s.window.end : BIZ_CLOSE);

            // Marca + etiqueta para cada hora en punto dentro del rango (no solo inicio/fin).
            var firstHour = Math.ceil(startM / 60), lastHour = Math.floor(endM / 60);
            var step = (lastHour - firstHour) > 8 ? 2 : 1;   // rango largo y angosto: etiqueta cada 2 h
            var hourLines = '', axis = '<span class="is-start" style="left:0">' + esc(startLabel) + '</span>';
            for (var h = firstHour; h <= lastHour; h++) {
                var hp = pct(h * 60);
                if (hp > 0 && hp < 100) {
                    hourLines += '<div class="agenda-daybar-hour" style="left:' + hp + '%"></div>';
                }
                if ((h - firstHour) % step === 0 && hp > 5 && hp < 95) {
                    axis += '<span style="left:' + hp + '%">' + esc(fmtHour(h)) + '</span>';
                }
            }
            axis += '<span class="is-end" style="left:100%">' + esc(endLabel) + '</span>';

            var blocks = (s.busy || []).map(function (b) {
                var l = pct(toMin(b.start)), r = pct(toMin(b.end));
                if (r <= l) return '';
                var w = r - l;
                var range = fmtHM(b.start) + '–' + fmtHM(b.end);
                var inner = w > (USE_24H ? 16 : 26) ? esc(range) : '';
                return '<div class="agenda-slot agenda-slot--busy" style="left:' + l + '%;width:' + w + '%" title="Ocupado ' + esc(range) + (b.label ? ' · ' + esc(b.label) : '') + '">' + inner + '</div>';
            }).join('');

            var busyMins = (s.busy || []).map(function (b) { return [toMin(b.start), toMin(b.end)]; });
            var sel = '', pickRow = '';
            if (selectedHHMM) {
                var selM = toMin(selectedHHMM);
                var dur = Math.max(5, durMin || 30);
                var selL = pct(selM);
                var selW = Math.max(0.6, pct(selM + dur) - selL);
                var clash = overlapsBusy(selM, dur, busyMins) || selM < startM || (selM + dur) > endM;
                var rng = fmtHM(selectedHHMM) + '–' + fmtHM(minToHHMM(selM + dur));
                sel = '<div class="agenda-daybar-sel' + (clash ? ' is-clash' : '') + '"' +
                      ' style="left:' + selL + '%;width:' + selW + '%"' +
                      ' data-w0="' + startM + '" data-w1="' + endM + '" data-dur="' + dur + '"' +
                      " data-busy='" + JSON.stringify(busyMins) + "'" +
                      ' title="' + esc(rng) + (clash ? ' · se encima con una cita' : '') + '"></div>';
                pickRow = '<div class="agenda-daybar-pick"><span style="left:' + selL + '%">▲ ' + esc(rng) + ' · ' + dur + ' min</span></div>' +
                          '<div class="agenda-daybar-draghint">Arrastra el bloque azul a un hueco libre.</div>';
            }

            return '<div class="agenda-daybar-legend">' +
                       '<span><i class="is-free"></i>Disponible</span>' +
                       '<span><i class="is-busy"></i>Ocupado</span>' +
                       (selectedHHMM ? '<span><i class="is-pick"></i>Esta cita</span>' : '') +
                   '</div>' +
                   '<div class="agenda-daybar">' + hourLines + blocks + sel + '</div>' +
                   '<div class="agenda-daybar-axis">' + axis + '</div>' +
                   pickRow;
        }

        function checkedServiceIds() {
            var ids = [];
            document.querySelectorAll('.service-card-input').forEach(function (cb) { if (cb.checked) ids.push(cb.value); });
            return ids;
        }
        // Fija el operador responsable (hidden + la 1ª línea de servicio marcada) al que
        // devolvió el buscador de hueco, cuando eligió un sustituto calificado.
        function forceResponsibleOperator(id) {
            var set = false;
            document.querySelectorAll('.service-card-label').forEach(function (card) {
                var cb = card.querySelector('.service-card-input');
                var sel = card.querySelector('.service-operator-select');
                if (!set && cb && cb.checked && sel) {
                    if ([].some.call(sel.options, function (o) { return o.value === String(id); })) {
                        sel.value = String(id); set = true;
                    }
                }
            });
            operatorSelect.value = String(id);
            syncScheduledAtState();
        }

        // Cuadro "Buscar el próximo hueco" — aparece en el panel cuando la selección no cabe.
        function nextSlotUi() {
            return '<div class="agenda-nextslot mt-2 pt-2 border-top">' +
                '<button type="button" class="btn btn-sm btn-primary" id="ns-go"><i class="bi bi-calendar-check"></i> Buscar el próximo hueco</button>' +
                '<button type="button" class="btn btn-sm btn-link p-0 ms-2 align-baseline" id="ns-opts">opciones de búsqueda</button>' +
                '<div id="ns-panel" class="mt-2 d-none">' +
                    '<div class="d-flex flex-wrap align-items-center gap-2">' +
                        '<label class="mb-0 text-body-secondary">Desde</label>' +
                        '<input type="date" id="ns-from" class="form-control form-control-sm" style="max-width:11rem" value="' + esc((scheduledAtInput.value || '').slice(0, 10)) + '">' +
                        '<label class="mb-0 text-body-secondary">Días a futuro</label>' +
                        '<input type="number" id="ns-days" class="form-control form-control-sm" style="max-width:5.5rem" min="1" max="90" value="30">' +
                    '</div>' +
                    '<div class="form-check mt-1">' +
                        '<input class="form-check-input" type="checkbox" id="ns-other">' +
                        '<label class="form-check-label" for="ns-other">Permitir otro operador calificado si este no tiene lugar</label>' +
                    '</div>' +
                '</div>' +
                '<div id="ns-result" class="mt-1"></div>' +
            '</div>';
        }

        function wireNextSlot() {
            var go = availabilityPanel.querySelector('#ns-go');
            if (!go) return;
            var optsBtn = availabilityPanel.querySelector('#ns-opts');
            var panel = availabilityPanel.querySelector('#ns-panel');
            var resEl = availabilityPanel.querySelector('#ns-result');
            if (optsBtn && panel) optsBtn.addEventListener('click', function () { panel.classList.toggle('d-none'); });

            go.addEventListener('click', function () {
                if (!operatorSelect.value) { resEl.className = 'mt-1 text-danger'; resEl.textContent = 'Elige un operador primero.'; return; }
                var daysEl = availabilityPanel.querySelector('#ns-days');
                var fromEl = availabilityPanel.querySelector('#ns-from');
                var otherEl = availabilityPanel.querySelector('#ns-other');
                var days = (daysEl && daysEl.value) || 30;
                go.disabled = true; resEl.className = 'mt-1 text-body-secondary'; resEl.textContent = 'Buscando…';

                var p = new URLSearchParams({
                    operator_id: operatorSelect.value,
                    duration_minutes: selectedDurationMinutes(),
                    from: (fromEl && fromEl.value) || (scheduledAtInput.value || '').slice(0, 10),
                    days: days,
                });
                if (otherEl && otherEl.checked) {
                    p.set('allow_other_qualified', '1');
                    checkedServiceIds().forEach(function (id) { p.append('service_ids[]', id); });
                }

                fetch('{{ route('agenda.next-slot') }}?' + p.toString(), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        go.disabled = false;
                        if (!d || !d.found) {
                            if (d && d.reason === 'no_common_operator') {
                                resEl.className = 'mt-1 fw-semibold text-danger';
                                resEl.textContent = 'Ningún operador hace todos estos servicios juntos; agéndalos por separado.';
                            } else {
                                resEl.className = 'mt-1 text-body-secondary';
                                resEl.textContent = 'No hay lugar en los próximos ' + days + ' días con este operador.';
                            }
                            return;
                        }
                        var changedOp = String(d.operator_id) !== String(operatorSelect.value);
                        if (changedOp) forceResponsibleOperator(d.operator_id);

                        var fp = scheduledAtInput && scheduledAtInput._flatpickr;
                        var dt = new Date(d.date + 'T' + d.time);
                        if (fp) fp.setDate(dt, false); else scheduledAtInput.value = d.date + 'T' + d.time;

                        resEl.className = 'mt-1 fw-semibold text-success';
                        resEl.textContent = '✓ ' + fmtHM(d.time) + ' · ' + d.date +
                            (changedOp ? ' — con ' + (d.operator_name || 'otro operador calificado') : '') +
                            (d.days_ahead > 0 ? ' (' + d.days_ahead + (d.days_ahead === 1 ? ' día' : ' días') + ')' : '') + '.';
                        checkAvailability();
                    })
                    .catch(function () { go.disabled = false; resEl.className = 'mt-1 text-danger'; resEl.textContent = 'Error al buscar el hueco.'; });
            });
        }

        function renderAvailability(data, selectedHHMM) {
            if (!availabilityPanel) return;
            var s = data.day_summary || {};
            var bits = [];

            if (s.window === null || s.window === undefined) {
                bits.push('Sin horario fijo capturado (puede a cualquier hora).');
            } else if (s.window.start && s.window.end) {
                bits.push('Labora ese día de <strong>' + esc(fmtHM(s.window.start)) + '</strong> a <strong>' + esc(fmtHM(s.window.end)) + '</strong>.');
            } else {
                bits.push('<strong>No labora ese día</strong> según su horario semanal.');
            }

            var busy = s.busy || [];
            if (busy.length) {
                bits.push('Ocupado: ' + busy.map(function (b) {
                    return esc(fmtHM(b.start)) + '–' + esc(fmtHM(b.end)) + (b.label && b.label !== 'Cita' ? ' (' + esc(b.label) + ')' : '');
                }).join(', ') + '.');
            } else {
                bits.push('Sin nada agendado ese día.');
            }

            var name = esc(data.operator_name || 'El operador');
            var ok = data.available !== false;
            availabilityPanel.className = 'mb-0 py-2 px-3 small alert ' + (ok ? 'alert-success' : 'alert-warning');
            var durMin = selectedDurationMinutes();

            // Misma ventana que dibuja dayTimeline — para alinear la barra de la estancia.
            var hasWin = !!(s.window && s.window.start && s.window.end);
            var w0 = hasWin ? toMin(s.window.start) : toMin(BIZ_OPEN);
            var w1 = hasWin ? toMin(s.window.end) : toMin(BIZ_CLOSE);

            renderStayStatus(data.resource);   // deja lastResourceData listo para stayBarRow

            availabilityPanel.innerHTML =
                '<strong>' + (ok ? '✓ ' : '⚠ ') + name + (ok ? ' está disponible en ese horario.' : ' — ' + esc(data.reason || 'revisa el horario.')) + '</strong><br>' +
                bits.join(' ') +
                dayTimeline(s, selectedHHMM, durMin) +
                stayBarRow(w0, w1) +
                (ok ? '' : nextSlotUi());

            // Barra del operador: mover la cita (y redimensionar su duración si hay un solo servicio).
            // `:not(.agenda-stay-bar)` — la barra de la estancia también es `.agenda-daybar`; sin
            // esto, si dayTimeline no dibujó nada, este selector caía sobre la de la jaula y la
            // cableaba con la lógica del operador además de la suya (doble asignación).
            var opBar = availabilityPanel.querySelector('.agenda-daybar:not(.agenda-stay-bar)');
            var opSel = opBar ? opBar.querySelector('.agenda-daybar-sel') : null;
            var opBusyMins = (s.busy || []).map(function (b) { return [toMin(b.start), toMin(b.end)]; });
            if (opSel) {
                var opBusy = [];
                try { opBusy = JSON.parse(opSel.dataset.busy || '[]'); } catch (e) { opBusy = []; }
                wireOperatorBlock(opBar, opSel, opBusy);
            }

            // Barra de la estancia en la jaula: mover/redimensionar Entra-Sale — solo si
            // `stayBarRow` la marcó interactiva (no cruza medianoche, ver su comentario).
            var stayBar = availabilityPanel.querySelector('.agenda-stay-bar');
            var staySel = stayBar ? stayBar.querySelector('.agenda-daybar-sel:not(.is-readonly)') : null;
            if (staySel) {
                var stayBusy = [];
                try { stayBusy = JSON.parse(staySel.dataset.busy || '[]'); } catch (e) { stayBusy = []; }
                wireStayBlock(stayBar, staySel, stayBusy);
            }

            if (!ok) wireNextSlot();

            // "Salta al cierre": si acabas de cambiar de día (o es el primer render, con la
            // hora por defecto "ahora + 1h" que no conoce la agenda real del operador) y la
            // hora propuesta no cabe ahí pero SÍ hay un hueco libre ese día, reacomodar sola al
            // más cercano (una vez, sin loop) — `dayJustChanged` arranca en `true` para cubrir
            // también la carga inicial, no solo un cambio de fecha explícito.
            if (!ok && dayJustChanged) {
                dayJustChanged = false;
                var selM0 = toMin(selectedHHMM);
                if (!isNaN(selM0) && w1 - w0 >= durMin) {
                    var fitted = snapToFit(selM0, w0, w1, durMin, opBusyMins);
                    if (fitted !== selM0 && fitted >= w0 && fitted + durMin <= w1 && !overlapsBusy(fitted, durMin, opBusyMins)) {
                        commitSelectedMinute(fitted);
                    }
                }
            }
        }

        var availReq = 0;
        // `checkAvailability` se llama desde muchos sitios (cambio de operador, servicio,
        // duración, fecha, campos de estancia…). Se debouncea para que una ráfaga de eventos
        // no dispare una tormenta de fetches ni de re-renders ("hace locuras").
        var _caTimer = null;
        function checkAvailability() {
            clearTimeout(_caTimer);
            _caTimer = setTimeout(_checkAvailabilityRun, 200);
        }
        function _checkAvailabilityRun() {
            if (!warningEl) return;
            // Hay un arrastre en curso (`dragCount > 0`) — no reemplazar el panel bajo el
            // cursor del usuario a mitad de un gesto; reintentar cuando se suelte (SYNC-095).
            if (dragCount > 0) { checkAvailability(); return; }
            syncStay();
            if (!operatorSelect.value || !scheduledAtInput.value) {
                warningEl.classList.add('d-none');
                overrideWrapper && overrideWrapper.classList.add('d-none');
                availabilityPanel && availabilityPanel.classList.add('d-none');
                return;
            }
            var params = new URLSearchParams({
                operator_id: operatorSelect.value,
                scheduled_at: scheduledAtInput.value.replace('T', ' '),
                duration_minutes: selectedDurationMinutes(),
            });
            if (resourceSelect && resourceSelect.value) {
                params.set('resource_id', resourceSelect.value);
                if (resStartHidden && resStartHidden.value) params.set('resource_starts_at', resStartHidden.value);
                if (resEndHidden && resEndHidden.value) params.set('resource_ends_at', resEndHidden.value);
            }
            var myReq = ++availReq;
            fetch('{{ route('agenda.check-availability') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (myReq !== availReq) return;   // ya llegó una respuesta más nueva
                    warningEl.textContent = data.reason || '';
                    warningEl.classList.toggle('d-none', data.available !== false);
                    if (overrideWrapper) {
                        var showOverride = data.available === false && data.can_override === true;
                        overrideWrapper.classList.toggle('d-none', !showOverride);
                        if (!showOverride && overrideCheckbox) overrideCheckbox.checked = false;
                    }
                    if (availabilityPanel) {
                        availabilityPanel.classList.remove('d-none');
                        renderAvailability(data, (scheduledAtInput.value || '').slice(11, 16));
                    }
                })
                .catch(function () {
                    if (myReq !== availReq) return;
                    warningEl.classList.add('d-none');
                    overrideWrapper && overrideWrapper.classList.add('d-none');
                    availabilityPanel && availabilityPanel.classList.add('d-none');
                });
        }

        // El operador "responsable" de la cita = el del primer servicio marcado que tenga
        // uno elegido. El JS lo mantiene en el hidden #operator_id (lo que valida el backend
        // y usa el chequeo de disponibilidad).
        function syncResponsibleOperator() {
            var chosen = '';
            document.querySelectorAll('.service-card-label').forEach(function (card) {
                if (chosen) return;
                var cb = card.querySelector('.service-card-input');
                var sel = card.querySelector('.service-operator-select');
                // "pending" ("por asignar") no cuenta como operador responsable de la cita.
                if (cb && cb.checked && sel && sel.value && sel.value !== 'pending') chosen = sel.value;
            });
            if (operatorSelect.value !== chosen) {
                operatorSelect.value = chosen;
                syncScheduledAtState();
                checkAvailability();
            }
        }

        var lastSchedDate = (scheduledAtInput && scheduledAtInput.value || '').slice(0, 10);
        // Arranca en `true`: la hora inicial es un default genérico ("ahora + 1h", sin conocer
        // la agenda real de ningún operador todavía — ver `$suggested` arriba) — el primer
        // `renderAvailability()` debe poder reacomodarla igual que un cambio de día explícito.
        var dayJustChanged = true;
        scheduledAtInput && scheduledAtInput.addEventListener('change', function () {
            var d = (scheduledAtInput.value || '').slice(0, 10);
            if (d && d !== lastSchedDate) { dayJustChanged = true; lastSchedDate = d; }
            checkAvailability();
        });
        resourceSelect && resourceSelect.addEventListener('change', function () {
            // Otra jaula (o ninguna): la salida vuelve a seguir al fin del servicio.
            stayEndTouched = false;
            checkAvailability();
        });

        // Duración y operador por servicio: solo se pueden editar cuando la tarjeta
        // del servicio está marcada.
        document.querySelectorAll('.service-card-input').forEach(function (cb) {
            var card = cb.closest('.service-card-label');
            if (!card) return;
            var configs = card.querySelectorAll('.service-config-input');
            function syncConfigState() {
                configs.forEach(function (el) { el.disabled = !cb.checked; });
            }
            cb.addEventListener('change', function () { syncConfigState(); syncResponsibleOperator(); checkAvailability(); });
            // Cambiar la duración de un servicio marcado redibuja el bloque de la cita a escala.
            card.querySelectorAll('input[name^="service_durations"]').forEach(function (d) {
                d.addEventListener('change', function () { if (cb.checked) checkAvailability(); });
            });
            syncConfigState();
        });
        document.querySelectorAll('.service-operator-select').forEach(function (sel) {
            sel.addEventListener('change', syncResponsibleOperator);
        });

        // Re-sella la ventana de estancia en los hidden justo antes de enviar + candado
        // anti doble-submit (un doble clic en "Guardar" creaba 2 citas → 2 asignaciones de
        // jaula; con la limpieza son 2 filas por cita, así que se veían "dobles/triples").
        var createForm = document.getElementById('agenda-create-form');
        var createSubmit = document.getElementById('agenda-create-submit');
        var submitting = false;
        createForm && createForm.addEventListener('submit', function (ev) {
            if (submitting) { ev.preventDefault(); return; }
            syncStay();
            submitting = true;
            if (createSubmit) { createSubmit.disabled = true; createSubmit.textContent = 'Guardando…'; }
        });

        syncResponsibleOperator();
        syncScheduledAtState();
        checkAvailability();
    });
</script>
@endpush

@endsection
