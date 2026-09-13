@php
    $screenDebugId = 'AgSpaEdi';
    $breadcrumbs = $page['breadcrumbs'];
    $selectedServiceIds = old('services', $booking->services->pluck('service_id')->map(fn($id) => (string)$id)->toArray());
@endphp
@extends('layouts.app')
@php($defaultScheduledAt = old('scheduled_at', $booking->scheduled_at?->format('Y-m-d\TH:i')))
@php($hasStayWindow = old('resource_starts_at') || $resourceStartsAt)
@php($defaultStayStart = old('resource_starts_at') ? str_replace(' ', 'T', old('resource_starts_at')) : optional($resourceStartsAt)->format('Y-m-d\TH:i'))
@php($defaultStayEnd = old('resource_ends_at') ? str_replace(' ', 'T', old('resource_ends_at')) : optional($resourceEndsAt)->format('Y-m-d\TH:i'))

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('agenda.show', $booking) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver al detalle
        </a>
    </x-slot:actions>
</x-page-header>

{{-- SYNC-099: aviso persistente de rechazo del guardado (ver nota en agenda/create.blade.php). --}}
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
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 text-uppercase text-body-secondary fw-bold mb-0">Datos de la Cita</h2>
                </div>
                <div class="card-body p-4">
                    <form id="agenda-edit-form" action="{{ route('agenda.update', $booking) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="operator_id" class="form-label fw-semibold">Operador</label>
                                <select id="operator_id" name="operator_id" class="form-select" required>
                                    <option value="">Selecciona un operador…</option>
                                    @foreach($operators as $operator)
                                        <option value="{{ $operator->id }}"
                                            @selected((string) old('operator_id', $booking->operator_id) === (string) $operator->id)>
                                            {{ $operator->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="scheduled_at" class="form-label fw-semibold">Fecha y hora</label>
                                <div id="scheduled_at_wrapper" class="{{ old('operator_id', $booking->operator_id) ? '' : 'is-locked' }}" style="position:relative;">
                                    <input id="scheduled_at" type="datetime-local" name="scheduled_at"
                                           value="{{ $defaultScheduledAt }}" class="form-control" required
                                           data-min-time="{{ $openingTime }}"
                                           data-max-time="{{ $closingTime }}">
                                </div>
                                <div id="scheduled_at_hint" class="form-text">Horario operativo: {{ \Illuminate\Support\Carbon::parse($openingTime)->format($timeFormat) }}–{{ \Illuminate\Support\Carbon::parse($closingTime)->format($timeFormat) }}.</div>
                                <div id="availability_warning" class="form-text text-danger d-none"></div>
                                <div id="override_availability_wrapper" class="form-check mt-1 d-none">
                                    <input type="checkbox" class="form-check-input" id="override_availability_checkbox" name="override_availability" value="1">
                                    <label class="form-check-label small" for="override_availability_checkbox">Agendar de todas formas, fuera del horario del operador</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="resource_id" class="form-label fw-semibold">Jaula / recurso físico</label>
                                <select id="resource_id" name="resource_id" class="form-select">
                                    <option value="">Sin asignar</option>
                                    @foreach($resources as $resource)
                                        <option value="{{ $resource->id }}"
                                            @selected((string) old('resource_id', $assignedResourceId) === (string) $resource->id)>
                                            {{ $resource->code }} · {{ $resource->name }}{{ $resource->administrative_status !== 'active' ? ' (inactivo)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                {{-- Estancia independiente del servicio (SYNC-093, misma pieza que AgSpaCre):
                                     entra y sale propios, con su propia barra de tiempo. Los hidden viajan
                                     al servidor; el datetime-local es solo para que el usuario ajuste a mano. --}}
                                <input type="hidden" name="resource_starts_at" id="resource_starts_at" value="{{ old('resource_starts_at', optional($resourceStartsAt)->format('Y-m-d H:i')) }}">
                                <input type="hidden" name="resource_ends_at" id="resource_ends_at" value="{{ old('resource_ends_at', optional($resourceEndsAt)->format('Y-m-d H:i')) }}">
                                {{-- SYNC-095 (9ª vuelta): la duración total de la cita ya se puede alargar/acortar
                                     arrastrando el borde derecho del bloque azul — viaja al servidor en este hidden. --}}
                                <input type="hidden" name="duration_minutes" id="duration_minutes_input" value="{{ old('duration_minutes', $durationMinutes) }}">

                                <div id="resource_stay" class="mt-2 p-2 rounded border small" hidden>
                                    <div class="fw-semibold mb-1">Estancia en la jaula</div>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <label for="resource_starts_at_input" class="mb-0 text-body-secondary" style="min-width:2.6rem;">Entra:</label>
                                            <input type="datetime-local" id="resource_starts_at_input" class="form-control form-control-sm" style="max-width: 15rem;" value="{{ $defaultStayStart }}">
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <label for="resource_ends_at_input" class="mb-0 text-body-secondary" style="min-width:2.6rem;">Sale:</label>
                                            <input type="datetime-local" id="resource_ends_at_input" class="form-control form-control-sm" style="max-width: 15rem;" value="{{ $defaultStayEnd }}">
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap mt-1">
                                        <button type="button" class="btn btn-link btn-sm p-0" data-stay-preset="service-start">Entra al iniciar el servicio</button>
                                        <button type="button" class="btn btn-link btn-sm p-0" data-stay-preset="service-end">Entra al terminar el servicio</button>
                                        <button type="button" class="btn btn-link btn-sm p-0" id="stay-snap">Ajustar al primer hueco libre de la jaula</button>
                                    </div>
                                    <div id="stay_status" class="mt-1 fw-semibold"></div>
                                    <div class="form-text mt-1">La barra de la estancia se ve en el panel de disponibilidad, debajo de la del operador. Ajústala con los campos Entra/Sale, los botones, o arrastrando el bloque ahí abajo.</div>
                                </div>

                                <div class="form-text">Si seleccionas jaula, la agenda bloqueará también {{ $resourceCleaningBufferMinutes }} min de limpieza al finalizar.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="notes" class="form-label fw-semibold">Notas operativas</label>
                                <textarea id="notes" name="notes" rows="4" class="form-control"
                                    placeholder="Ajustes para recepción, grooming o coordinación">{{ old('notes', $booking->notes) }}</textarea>
                            </div>
                            <div class="col-12">
                                <div id="availability_panel" class="alert d-none mb-0 py-2 px-3 small"></div>
                            </div>
                        </div>

                        @if($booking->status === 'scheduled')
                        <hr>
                        <h6 class="text-uppercase small text-body-secondary fw-bold mb-3">Servicios</h6>
                        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-2 mb-4">
                            @foreach($services as $service)
                                @php($checked = in_array((string)$service->id, $selectedServiceIds, true))
                                <div class="col">
                                    <label class="card h-100 border-2 {{ $checked ? 'border-primary bg-primary-subtle' : 'border' }}"
                                           style="cursor:pointer;">
                                        <div class="card-body p-2 d-flex align-items-start gap-2">
                                            <input type="checkbox" name="services[]" value="{{ $service->id }}"
                                                   class="form-check-input mt-1 flex-shrink-0" @checked($checked)>
                                            <div>
                                                <div class="fw-semibold small lh-sm">{{ $service->name }}</div>
                                                <div class="text-muted" style="font-size:0.7rem;">${{ number_format($service->price ?? 0, 2) }}</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @else
                        <hr>
                        <h6 class="text-uppercase small text-body-secondary fw-bold mb-2">Servicios (no editables en este estado)</h6>
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            @foreach($booking->services as $bs)
                                <span class="badge bg-light text-dark border">{{ $bs->service?->name ?? 'Servicio' }}</span>
                            @endforeach
                        </div>
                        @endif

                        <div class="d-flex gap-2 flex-wrap justify-content-end">
                            <a href="{{ route('agenda.show', $booking) }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" id="agenda-edit-submit" class="btn btn-primary fw-bold">
                                <i class="bi bi-check2 me-1"></i> Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h2 class="h6 text-uppercase text-body-secondary fw-bold mb-3">Resumen de la cita</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-body-secondary">Mascota</dt>
                        <dd class="col-7 fw-semibold">{{ $pet?->name ?: '—' }}</dd>

                        <dt class="col-5 text-body-secondary">Cliente</dt>
                        <dd class="col-7">{{ trim(($client?->first_name ?? '') . ' ' . ($client?->last_name ?? '')) ?: '—' }}</dd>

                        <dt class="col-5 text-body-secondary">Estado</dt>
                        <dd class="col-7">
                            <span class="badge text-bg-{{ match($booking->status) {
                                'scheduled' => 'secondary',
                                'work_order' => 'warning',
                                'completed' => 'success',
                                default => 'light'
                            } }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                        </dd>

                        <dt class="col-5 text-body-secondary">Programada</dt>
                        <dd class="col-7">{{ $booking->scheduled_at?->format($datetimeFormat) ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    #scheduled_at_wrapper.is-locked {
        opacity: .5;
        pointer-events: none;
    }
    /* Barra de la agenda del día del operador en el panel de disponibilidad — copiado tal cual
       de agenda/create.blade.php (SYNC-093); mismo widget, dos pantallas. Ver el comentario ahí
       sobre por qué no se factorizó a un partial compartido todavía. */
    .agenda-daybar {
        position: relative;
        height: 28px;
        border-radius: 4px;
        background: repeating-linear-gradient(90deg, rgba(25,135,84,.10) 0 8px, rgba(25,135,84,.16) 8px 16px);
        border: 1px solid rgba(0,0,0,.1);
        overflow: hidden;
    }
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
    .agenda-daybar-sel.is-readonly {
        cursor: default;
        touch-action: auto;
        pointer-events: none;
    }
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
        // A diferencia de AgSpaCre, aquí no hay tarjetas de servicio con duración editable por
        // línea — pero (SYNC-095, 9ª vuelta) la duración TOTAL de la cita sí se puede cambiar,
        // arrastrando el borde derecho del bloque azul (no afecta servicios individuales, solo
        // el tamaño del bloque completo en la agenda). Arranca con lo que calculó el servidor
        // (`$durationMinutes` — la propia de la cita si ya la tenía, si no la suma de catálogo).
        var durationHiddenInput = document.getElementById('duration_minutes_input');
        var currentDurationMinutes = @json($durationMinutes) || 30;
        var EXCLUDE_BOOKING_ID = @json($booking->id);

        function esc(v) {
            var d = document.createElement('div');
            d.textContent = v == null ? '' : String(v);
            return d.innerHTML;
        }

        function toMin(hhmm) {
            var p = String(hhmm || '').split(':');
            return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
        }

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

        function selectedDurationMinutes() { return currentDurationMinutes; }

        function overlapsBusy(startM, dur, busy) {
            for (var i = 0; i < busy.length; i++) {
                if (startM < busy[i][1] && startM + dur > busy[i][0]) return true;
            }
            return false;
        }

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
            if (w1 > cursor) gaps.push([cursor, w1]);
            var best = null, bestDist = Infinity;
            gaps.forEach(function (g) {
                if (g[1] - g[0] < dur) return;
                var cand = Math.max(g[0], Math.min(g[1] - dur, desired));
                var dist = Math.abs(cand - desired);
                if (dist < bestDist) { bestDist = dist; best = cand; }
            });
            return best != null ? best : desired;
        }

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

        // ── Estancia en la jaula ─────────────────────────────────────────────────────────
        var resourceStayBox = document.getElementById('resource_stay');
        var resourceStartsInput = document.getElementById('resource_starts_at_input');
        var resourceEndsInput = document.getElementById('resource_ends_at_input');
        var stayStatusEl = document.getElementById('stay_status');
        var resStartHidden = document.getElementById('resource_starts_at');
        var resEndHidden = document.getElementById('resource_ends_at');
        // A diferencia de AgSpaCre (siempre arranca sin tocar), aquí puede haber una estancia
        // real ya guardada — si la hay, no se pisa con el default "fin del servicio + 1h".
        var stayStartTouched = @json($hasStayWindow);
        var stayEndTouched = @json($hasStayWindow);
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
            if (syncingStay) return;
            syncingStay = true;
            try {
                if (!stayStartTouched) setFp(resourceStartsInput, serviceEndDate());
                if (!stayEndTouched) setFp(resourceEndsInput, new Date(serviceEndDate().getTime() + 60 * 60000));
                if (stayEndD() <= stayStartD()) setFp(resourceEndsInput, new Date(stayStartD().getTime() + 30 * 60000));
                var fpe = resourceEndsInput && resourceEndsInput._flatpickr;
                if (fpe) fpe.set('minDate', stayStartD());
            } finally {
                syncingStay = false;
            }
            if (resStartHidden) resStartHidden.value = toSpace(toLocalInput(stayStartD()));
            if (resEndHidden) resEndHidden.value = toSpace(toLocalInput(stayEndD()));
        }

        // Barra de la estancia — misma pieza que `stayBarRow` de AgSpaCre (SYNC-091: interactiva
        // salvo cruce de medianoche). Ver el comentario largo en create.blade.php sobre por qué
        // es seguro que se arrastre (no escribe nada al servidor durante el arrastre).
        function stayBarRow(w0, w1) {
            if (!(resourceSelect && resourceSelect.value && scheduledAtInput.value)) return '';
            var span = w1 - w0;
            if (!(span > 0)) return '';

            var s = stayStartD(), e = stayEndD();
            var multiDay = !sameDay(s, e);
            var sm = minOfDay(s);
            var em = multiDay ? w1 : minOfDay(e);
            var svc = sameDay(s, serviceStartDate())
                ? [minOfDay(serviceStartDate()), minOfDay(serviceEndDate())] : null;
            var res = lastResourceData || {};

            var p = function (m) { return Math.max(0, Math.min(100, ((m - w0) / span) * 100)); };

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
            var clash = res.available === false;
            var titleTxt = multiDay
                ? 'Estancia ' + fmtDT(s) + ' → ' + fmtDT(e)
                : 'Estancia ' + fmtHM(minToHHMM(sm)) + '–' + fmtHM(minToHHMM(minOfDay(e)));
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
                if (syncingStay) return;
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
        // cursor del usuario. Ver el comentario largo de `attachBlockDrag`.
        var dragCount = 0;

        // Arrastrar/redimensionar un bloque sobre una barra — idéntico a `attachBlockDrag` de
        // create.blade.php (SYNC-091), con dos correcciones de SYNC-095 (3ª vuelta) que faltan
        // portar allá también (ver `PENDIENTES_SINCRONIZAR_ESTETICAN.md`):
        //
        // Tomas: "al arrastrar el bloque de jaula sobre el espacio marcado como de servicio, el
        // tamaño del bloque de reserva (morado) se redimensiona si queda aunque sea un pedazo
        // sobre esa area" — dos causas reales encontradas:
        //
        // 1. `EDGE` (zona de "tomar el borde para redimensionar") era un ancho fijo de 10px sin
        //    importar qué tan angosto sea el bloque ya dibujado — en una estancia corta (15–30
        //    min sobre una barra de varias horas, ~20px de ancho ya dibujado) esos 10px por lado
        //    se comen casi todo el bloque, así que casi cualquier clic para "moverlo" agarraba en
        //    realidad un borde y lo REDIMENSIONABA (un extremo fijo, el otro sigue al cursor) en
        //    vez de moverlo entero — se ve igual que "el bloque cambia de tamaño solo" al
        //    arrastrar. Confirmado con arrastre real simulado (Playwright, no solo lectura de
        //    código) contra `tst`: un primer intento de arreglo (borde ≤ un tercio del ancho)
        //    seguía dejando muy poco margen central en un bloque de 15 min (~6px de 20px) — casi
        //    cualquier clic real seguía cayendo en zona de borde. Ahora el centro para mover
        //    nunca es menor a 16px, sin importar qué tan angosto esté el bloque.
        // 2. Un `checkAvailability()` pendiente (de un cambio justo antes de empezar a arrastrar)
        //    podía disparar su fetch/rerender a mitad del arrastre y reemplazar TODO el panel
        //    (`availabilityPanel.innerHTML`) con la posición ya guardada — el bloque que se veía
        //    en pantalla "saltaba" de vuelta a como estaba antes de esta arrastrada. Ahora
        //    `_checkAvailabilityRun()` se reprograma solo (sin pedir nada al servidor) mientras
        //    haya un arrastre activo, y corre en cuanto se suelta.
        function attachBlockDrag(barEl, selEl, cfg) {
            if (!barEl || !selEl || cfg.w1 - cfg.w0 <= 0) return;
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
                // MIN_MOVE_ZONE: ver el comentario largo arriba de `attachBlockDrag` — a costa de
                // que un bloque muy corto casi no deje margen para tomar el borde a propósito
                // (aceptable: ya está en el mínimo de 15 min, hay poco que ajustar de todos modos).
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

        // Estado actual del bloque de la cita (historial completo de las nueve vueltas de
        // `SYNC-095` en `PENDIENTES_SINCRONIZAR_ESTETICAN.md`, no repetido acá):
        // - Se puede mover (arrastrar el cuerpo) Y redimensionar (arrastrar el borde derecho,
        //   `edges: 'end'` — cambia la duración total de la cita, no la de un servicio en
        //   particular; el izquierdo no se usa, para mover ya está el cuerpo completo).
        // - Sobre su PROPIO horario actual, totalmente libre — nunca se reubica ni se ajusta
        //   solo (`SYNC-094` ya excluye la cita propia de `day_summary.busy`).
        // - Sobre un choque REAL con OTRA cita del mismo operador, al soltar: no se guarda esa
        //   posición — se revierte a como estaba (no se llama a `commitSelectedMinute`, así que
        //   el próximo `checkAvailability()` re-renderiza con los datos de siempre, sin cambios)
        //   y se avisa con `flashOccupiedMessage()`, un mensaje flotante que desaparece solo.
        // Aviso corto y flotante, fuera del panel de disponibilidad — sobrevive a que
        // `checkAvailability()` reemplace el panel (`availabilityPanel.innerHTML`) justo
        // después, a diferencia de cualquier elemento que viviera adentro.
        function flashOccupiedMessage() {
            var el = document.createElement('div');
            el.textContent = '⚠ Ocupado — hay otra cita en ese horario';
            el.style.cssText = 'position:fixed;top:1rem;left:50%;transform:translateX(-50%);'
                + 'background:#dc3545;color:#fff;padding:.45rem 1rem;border-radius:4px;'
                + 'font-size:.85rem;font-weight:600;z-index:2000;box-shadow:0 2px 10px rgba(0,0,0,.25);'
                + 'opacity:0;transition:opacity .15s;pointer-events:none;';
            document.body.appendChild(el);
            requestAnimationFrame(function () { el.style.opacity = '1'; });
            setTimeout(function () {
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 200);
            }, 1600);
        }

        function wireOperatorBlock(bar, sel, busy) {
            var w0 = +sel.dataset.w0, w1 = +sel.dataset.w1;
            if (isNaN(w0) || isNaN(w1)) return;
            attachBlockDrag(bar, sel, {
                w0: w0, w1: w1, minDur: 5, edges: 'end',
                getRange: function () { var m = toMin(currentSelectedHHMM()); return [m, m + selectedDurationMinutes()]; },
                onDraw: function (st, en) {
                    sel.classList.toggle('is-clash', overlapsBusy(st, en - st, busy));
                    sel.title = fmtHM(minToHHMM(st)) + '–' + fmtHM(minToHHMM(en));
                },
                onCommit: function (st, en, mode) {
                    if (overlapsBusy(st, en - st, busy)) {
                        flashOccupiedMessage();
                        checkAvailability(); // sin cambios reales que commitear — el re-render deja el bloque donde ya estaba
                        return;
                    }
                    if (mode === 'end') {
                        currentDurationMinutes = en - st;
                        if (durationHiddenInput) durationHiddenInput.value = currentDurationMinutes;
                    }
                    commitSelectedMinute(st);
                },
            });
        }

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
                    // arrastre mueve la estancia completa a una hora más temprana (p. ej. de
                    // después del servicio a antes), el nuevo "Sale" cae ANTES de ese `minDate`
                    // viejo — flatpickr rechaza la fecha en silencio y el campo queda vacío.
                    // `stayEndD()` entonces cae a su valor por defecto (`serviceEndDate()`), una
                    // ventana mucho más ancha que la real — se ve como "la jaula se redimensiona
                    // sola" al mover el bloque (Tomas, con capturas: la estancia pasó de ~1h
                    // después del servicio a ~3h antes, sin que el ancho arrastrado cambiara
                    // durante el gesto). Se relaja el límite antes de fijar el valor nuevo;
                    // `syncStay()`, un par de líneas abajo, ya pone el `minDate` correcto (basado
                    // en el Entra nuevo) para el próximo arrastre.
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

            var firstHour = Math.ceil(startM / 60), lastHour = Math.floor(endM / 60);
            var step = (lastHour - firstHour) > 8 ? 2 : 1;
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
                          '<div class="agenda-daybar-draghint">Arrastra el bloque azul para moverlo, o su borde derecho para cambiar la duración.</div>';
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

        // "Buscar el próximo hueco" — mismo cuadro que AgSpaCre, sin la parte de "operador
        // calificado alterno" (ahí depende de tarjetas de servicio por línea que aquí no existen).
        function checkedServiceIds() {
            var ids = [];
            document.querySelectorAll('input[name="services[]"]:checked').forEach(function (cb) { ids.push(cb.value); });
            return ids;
        }
        function forceResponsibleOperator(id) {
            operatorSelect.value = String(id);
            syncScheduledAtState();
        }
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
                var days = (daysEl && daysEl.value) || 30;
                go.disabled = true; resEl.className = 'mt-1 text-body-secondary'; resEl.textContent = 'Buscando…';

                var p = new URLSearchParams({
                    operator_id: operatorSelect.value,
                    duration_minutes: selectedDurationMinutes(),
                    from: (fromEl && fromEl.value) || (scheduledAtInput.value || '').slice(0, 10),
                    days: days,
                    exclude_booking_id: EXCLUDE_BOOKING_ID,
                });

                fetch('{{ route('agenda.next-slot') }}?' + p.toString(), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        go.disabled = false;
                        if (!d || !d.found) {
                            resEl.className = 'mt-1 text-body-secondary';
                            resEl.textContent = 'No hay lugar en los próximos ' + days + ' días con este operador.';
                            return;
                        }
                        var fp = scheduledAtInput && scheduledAtInput._flatpickr;
                        var dt = new Date(d.date + 'T' + d.time);
                        if (fp) fp.setDate(dt, false); else scheduledAtInput.value = d.date + 'T' + d.time;

                        resEl.className = 'mt-1 fw-semibold text-success';
                        resEl.textContent = '✓ ' + fmtHM(d.time) + ' · ' + d.date +
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

            var hasWin = !!(s.window && s.window.start && s.window.end);
            var w0 = hasWin ? toMin(s.window.start) : toMin(BIZ_OPEN);
            var w1 = hasWin ? toMin(s.window.end) : toMin(BIZ_CLOSE);

            renderStayStatus(data.resource);

            availabilityPanel.innerHTML =
                '<strong>' + (ok ? '✓ ' : '⚠ ') + name + (ok ? ' está disponible en ese horario.' : ' — ' + esc(data.reason || 'revisa el horario.')) + '</strong><br>' +
                bits.join(' ') +
                dayTimeline(s, selectedHHMM, durMin) +
                stayBarRow(w0, w1) +
                (ok ? '' : nextSlotUi());

            var opBar = availabilityPanel.querySelector('.agenda-daybar:not(.agenda-stay-bar)');
            var opSel = opBar ? opBar.querySelector('.agenda-daybar-sel') : null;
            if (opSel) {
                var opBusy = [];
                try { opBusy = JSON.parse(opSel.dataset.busy || '[]'); } catch (e) { opBusy = []; }
                wireOperatorBlock(opBar, opSel, opBusy);
            }

            var stayBar = availabilityPanel.querySelector('.agenda-stay-bar');
            var staySel = stayBar ? stayBar.querySelector('.agenda-daybar-sel:not(.is-readonly)') : null;
            if (staySel) {
                var stayBusy = [];
                try { stayBusy = JSON.parse(staySel.dataset.busy || '[]'); } catch (e) { stayBusy = []; }
                wireStayBlock(stayBar, staySel, stayBusy);
            }

            if (!ok) wireNextSlot();

            // A diferencia de AgSpaCre, aquí NUNCA se reacomoda sola la hora — ni al cargar la
            // página ni al cambiar de día. `AgSpaCre` sí lo hace ("salta al cierre") porque su
            // hora inicial es un default genérico que no conoce la agenda real de nadie; en
            // `AgSpaEdi` la hora que se ve es la de una cita YA agendada, elegida a propósito.
            // Tomas (tras `SYNC-094`): "deberían estar totalmente sueltos por ser edición" — si
            // el horario actual ya no cabe, se muestra el aviso y el bloque en rojo, pero el
            // usuario decide si arrastrarlo, usar "Buscar el próximo hueco" o dejarlo así.
        }

        var availReq = 0;
        var _caTimer = null;
        function checkAvailability() {
            clearTimeout(_caTimer);
            _caTimer = setTimeout(_checkAvailabilityRun, 200);
        }
        function _checkAvailabilityRun() {
            if (!warningEl) return;
            // Hay un arrastre en curso (`dragCount > 0`) — no reemplazar el panel bajo el
            // cursor del usuario a mitad de un gesto; reintentar cuando se suelte.
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
                exclude_booking_id: EXCLUDE_BOOKING_ID,
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
                    if (myReq !== availReq) return;
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

        scheduledAtInput && scheduledAtInput.addEventListener('change', function () {
            checkAvailability();
        });
        resourceSelect && resourceSelect.addEventListener('change', function () {
            stayEndTouched = false;
            checkAvailability();
        });

        // Candado anti doble-submit — misma pieza que `SYNC-086` vuelta 6 en AgSpaCre.
        var editForm = document.getElementById('agenda-edit-form');
        var editSubmit = document.getElementById('agenda-edit-submit');
        var submitting = false;
        editForm && editForm.addEventListener('submit', function (ev) {
            if (submitting) { ev.preventDefault(); return; }
            syncStay();
            submitting = true;
            if (editSubmit) { editSubmit.disabled = true; editSubmit.textContent = 'Guardando…'; }
        });

        operatorSelect.addEventListener('change', function () { syncScheduledAtState(); checkAvailability(); });
        syncScheduledAtState();
        checkAvailability();
    });
</script>
@endpush
@endsection
