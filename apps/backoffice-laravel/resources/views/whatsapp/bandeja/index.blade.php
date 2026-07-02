@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('whatsapp.plantillas.index') }}" class="btn btn-outline-secondary">Plantillas de mensaje</a>
    </x-slot:actions>
</x-page-header>

@php
    $selectedDate = \Carbon\Carbon::parse($date);
@endphp

<div
    class="catalog-content-wide"
    x-data="whatsappBandeja({
        csrfToken: '{{ csrf_token() }}',
        sendUrlTemplate: '{{ route('whatsapp.bandeja.enviar', ['booking' => '__ID__']) }}',
        rows: {{ \Illuminate\Support\Js::from($rows->map(fn ($r) => [
            'id' => $r->booking->id,
            'label' => trim(($r->booking->pet?->client?->full_name ?: 'Cliente') . ' — ' . ($r->booking->pet?->name ?: 'Mascota')),
        ])->values()) }},
    })"
>
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('whatsapp.bandeja', ['date' => $selectedDate->copy()->subDay()->toDateString()]) }}" class="btn btn-outline-secondary btn-sm">&laquo; Día anterior</a>
        <a href="{{ route('whatsapp.bandeja', ['date' => now()->toDateString()]) }}" class="btn btn-outline-secondary btn-sm {{ $selectedDate->isToday() ? 'active' : '' }}">Hoy</a>
        <a href="{{ route('whatsapp.bandeja', ['date' => now()->addDay()->toDateString()]) }}" class="btn btn-outline-secondary btn-sm {{ $selectedDate->isTomorrow() ? 'active' : '' }}">Mañana</a>
        <a href="{{ route('whatsapp.bandeja', ['date' => $selectedDate->copy()->addDay()->toDateString()]) }}" class="btn btn-outline-secondary btn-sm">Día siguiente &raquo;</a>

        <form method="GET" action="{{ route('whatsapp.bandeja') }}" class="d-flex align-items-center gap-2 ms-auto">
            <input type="date" name="date" value="{{ $selectedDate->toDateString() }}" class="form-control form-control-sm" style="width:auto">
            <button type="submit" class="btn btn-outline-primary btn-sm">Ver fecha</button>
        </form>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <label class="fw-semibold mb-0">Plantilla a usar:</label>
            <select class="form-select" style="max-width:360px" x-model="templateId">
                <option value="">Selecciona una plantilla…</option>
                @foreach($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-success ms-auto" @click="openQueue()">
                Enviar seleccionados
            </button>
        </div>
        @if($templates->isEmpty())
            <div class="card-footer bg-warning-subtle text-warning-emphasis small">
                No hay plantillas activas. <a href="{{ route('whatsapp.plantillas.create') }}">Crea una plantilla</a> antes de enviar recordatorios.
            </div>
        @endif
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">
                            <input type="checkbox" class="form-check-input"
                                @change="toggleAll($event.target.checked, [{{ $rows->filter(fn ($r) => $r->wa_number)->pluck('booking.id')->implode(',') }}])">
                        </th>
                        <th style="width:90px">Hora</th>
                        <th>Mascota</th>
                        <th>Cliente</th>
                        <th>Teléfono</th>
                        <th>Servicio(s)</th>
                        <th style="width:120px">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $booking = $row->booking;
                            $client = $booking->pet?->client;
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input"
                                    value="{{ $booking->id }}"
                                    x-model="selected"
                                    @disabled(! $row->wa_number)>
                            </td>
                            <td>{{ $booking->scheduled_at->format($timeFormat) }}</td>
                            <td>{{ $booking->pet?->name ?? '—' }}</td>
                            <td>{{ $client?->full_name ?? '—' }}</td>
                            <td>
                                @if($row->wa_number)
                                    <span class="text-body">+{{ $row->wa_number }}</span>
                                @elseif($row->raw_phone)
                                    <span class="text-danger" title="No reconocido como teléfono mexicano de 10 dígitos">{{ $row->raw_phone }} ⚠️</span>
                                @else
                                    <span class="text-muted">Sin teléfono</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $booking->services->pluck('service.name')->filter()->implode(', ') ?: '—' }}</td>
                            <td>
                                @if($row->already_sent_today)
                                    <span class="badge bg-success-subtle text-success-emphasis">Enviado hoy</span>
                                @else
                                    <span class="badge bg-light text-muted">Pendiente</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No hay citas SPA programadas para esta fecha.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal de envío secuencial --}}
    <div class="modal fade" id="modalSendQueue" x-ref="sendModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Enviar recordatorios</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <template x-if="!isDone">
                        <div>
                            <p class="text-muted mb-1" x-text="`${queueIndex + 1} de ${queue.length}`"></p>
                            <h5 x-text="currentLabel"></h5>
                            <p class="small text-muted">Se abrirá WhatsApp en una pestaña nueva con el mensaje ya redactado. Confirma el envío ahí.</p>
                        </div>
                    </template>
                    <template x-if="isDone">
                        <div class="text-center py-3">
                            <p class="mb-0 fw-semibold">Listo — recargando la bandeja…</p>
                        </div>
                    </template>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-link link-secondary" @click="skipCurrent()" x-show="!isDone">Omitir</button>
                    <button type="button" class="btn btn-success" @click="sendCurrent()" x-show="!isDone" :disabled="sending">
                        <span x-show="!sending">Abrir WhatsApp</span>
                        <span x-show="sending">Enviando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
