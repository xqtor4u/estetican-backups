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

            <form action="{{ $isRootView ? route('pets.bookings.store', $pet) : route('clients.pets.bookings.store', [$client, $pet]) }}" method="POST">
                @csrf
                @if($isRootView)
                    <input type="hidden" name="return_view_mode" value="{{ $returnViewMode }}">
                @endif

                {{-- El operador se elige por servicio (más abajo). El "responsable" de la cita
                     es el del primer servicio marcado — este hidden lo mantiene sincronizado el JS. --}}
                <input type="hidden" id="operator_id" name="operator_id" value="{{ old('operator_id') }}">

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
                                data-force-24h="1"
                                data-min-time="{{ $openingTime }}"
                                data-max-time="{{ $closingTime }}"
                            >
                        </div>
                        <div id="scheduled_at_hint" class="form-text">Horario operativo: {{ $openingTime }}–{{ $closingTime }}.</div>
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

                <div class="catalog-filter-note mb-4">
                    <span class="catalog-filter-note__kicker">Selección de servicios</span>
                    <p class="catalog-filter-note__text mb-0">Elige uno o más servicios activos. El precio se sugiere del catálogo, pero podés editarlo antes de guardar.</p>
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
                                    <div class="d-flex flex-column gap-1 mb-3">
                                        <span class="catalog-inline-tag text-truncate" title="{{ $service->operatorRole ? 'Solo lo puede realizar un operador con el rol: '.$service->operatorRole->name : 'No exige un rol de operador — lo puede realizar cualquier operador activo' }}"><i class="bi bi-person me-1"></i> {{ $service->operatorRole?->name ?? 'Cualquier operador' }}</span>
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
                                                @foreach($operators as $operator)
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

                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <a href="{{ $isRootView ? route('pets.show', ['pet' => $pet, 'view' => $returnViewMode]) : route('clients.pets.show', [$client, $pet]) }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar programación</button>
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
        height: 22px;
        border-radius: 4px;
        background: repeating-linear-gradient(90deg, rgba(25,135,84,.10) 0 8px, rgba(25,135,84,.16) 8px 16px);
        border: 1px solid rgba(0,0,0,.1);
        overflow: hidden;
    }
    .agenda-slot--busy {
        position: absolute;
        top: 0; bottom: 0;
        background: rgba(220,53,69,.55);
        border-left: 1px solid rgba(220,53,69,.9);
        border-right: 1px solid rgba(220,53,69,.9);
    }
    .agenda-slot-marker {
        position: absolute;
        top: -3px; bottom: -3px;
        width: 2px;
        background: #0d6efd;
        box-shadow: 0 0 0 2px rgba(13,110,253,.25);
    }
    .agenda-daybar-scale {
        font-size: .68rem;
        opacity: .7;
    }
</style>
@endpush

@push('scripts')
<script nonce="{{ csp_nonce() }}">
    document.addEventListener('DOMContentLoaded', function () {
        var operatorSelect = document.getElementById('operator_id');
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

        function dayTimeline(s, selectedHHMM) {
            var startM, endM;
            if (s.window && s.window.start && s.window.end) {
                startM = toMin(s.window.start); endM = toMin(s.window.end);
            } else {
                startM = toMin(BIZ_OPEN); endM = toMin(BIZ_CLOSE);
            }
            if (endM <= startM) return '';
            var total = endM - startM;
            var pct = function (m) { return Math.max(0, Math.min(100, ((m - startM) / total) * 100)); };

            var blocks = (s.busy || []).map(function (b) {
                var l = pct(toMin(b.start)), r = pct(toMin(b.end));
                if (r <= l) return '';
                return '<div class="agenda-slot agenda-slot--busy" style="left:' + l + '%;width:' + (r - l) + '%" title="' + esc(b.start) + '–' + esc(b.end) + (b.label ? ' · ' + esc(b.label) : '') + '"></div>';
            }).join('');

            var marker = '';
            if (selectedHHMM) {
                var mp = pct(toMin(selectedHHMM));
                marker = '<div class="agenda-slot-marker" style="left:' + mp + '%" title="Hora elegida: ' + esc(selectedHHMM) + '"></div>';
            }

            return '<div class="agenda-daybar mt-2">' + blocks + marker + '</div>' +
                   '<div class="d-flex justify-content-between agenda-daybar-scale"><span>' + esc(startM === toMin(BIZ_OPEN) ? BIZ_OPEN : s.window.start) + '</span><span>' + esc(endM === toMin(BIZ_CLOSE) ? BIZ_CLOSE : s.window.end) + '</span></div>';
        }

        function renderAvailability(data, selectedHHMM) {
            if (!availabilityPanel) return;
            var s = data.day_summary || {};
            var bits = [];

            if (s.window === null || s.window === undefined) {
                bits.push('Sin horario fijo capturado (puede a cualquier hora).');
            } else if (s.window.start && s.window.end) {
                bits.push('Labora ese día de <strong>' + esc(s.window.start) + '</strong> a <strong>' + esc(s.window.end) + '</strong>.');
            } else {
                bits.push('<strong>No labora ese día</strong> según su horario semanal.');
            }

            var busy = s.busy || [];
            if (busy.length) {
                bits.push('Ocupado: ' + busy.map(function (b) {
                    return esc(b.start) + '–' + esc(b.end) + (b.label && b.label !== 'Cita' ? ' (' + esc(b.label) + ')' : '');
                }).join(', ') + '.');
            } else {
                bits.push('Sin nada agendado ese día.');
            }

            var name = esc(data.operator_name || 'El operador');
            var ok = data.available !== false;
            availabilityPanel.className = 'mb-0 py-2 px-3 small alert ' + (ok ? 'alert-success' : 'alert-warning');
            availabilityPanel.innerHTML =
                '<strong>' + (ok ? '✓ ' : '⚠ ') + name + (ok ? ' está disponible en ese horario.' : ' — ' + esc(data.reason || 'revisa el horario.')) + '</strong><br>' +
                bits.join(' ') +
                dayTimeline(s, selectedHHMM);
        }

        function checkAvailability() {
            if (!warningEl) return;
            if (!operatorSelect.value || !scheduledAtInput.value) {
                warningEl.classList.add('d-none');
                overrideWrapper && overrideWrapper.classList.add('d-none');
                availabilityPanel && availabilityPanel.classList.add('d-none');
                return;
            }
            var params = new URLSearchParams({
                operator_id: operatorSelect.value,
                scheduled_at: scheduledAtInput.value.replace('T', ' '),
            });
            fetch('{{ route('agenda.check-availability') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
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
                if (cb && cb.checked && sel && sel.value) chosen = sel.value;
            });
            if (operatorSelect.value !== chosen) {
                operatorSelect.value = chosen;
                syncScheduledAtState();
                checkAvailability();
            }
        }

        scheduledAtInput && scheduledAtInput.addEventListener('change', checkAvailability);

        // Duración y operador por servicio: solo se pueden editar cuando la tarjeta
        // del servicio está marcada.
        document.querySelectorAll('.service-card-input').forEach(function (cb) {
            var card = cb.closest('.service-card-label');
            if (!card) return;
            var configs = card.querySelectorAll('.service-config-input');
            function syncConfigState() {
                configs.forEach(function (el) { el.disabled = !cb.checked; });
            }
            cb.addEventListener('change', function () { syncConfigState(); syncResponsibleOperator(); });
            syncConfigState();
        });
        document.querySelectorAll('.service-operator-select').forEach(function (sel) {
            sel.addEventListener('change', syncResponsibleOperator);
        });

        syncResponsibleOperator();
        syncScheduledAtState();
        checkAvailability();
    });
</script>
@endpush

@endsection