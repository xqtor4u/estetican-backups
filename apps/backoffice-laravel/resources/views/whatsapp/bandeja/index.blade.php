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
        previewUrlTemplate: '{{ route('whatsapp.bandeja.preview', ['booking' => '__ID__']) }}',
        createTemplateUrl: '{{ route('whatsapp.plantillas.store') }}',
        templateContext: 'cita',
        templateVariables: {{ \Illuminate\Support\Js::from($templateVariables) }},
        templates: {{ \Illuminate\Support\Js::from($templates->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()) }},
        rows: {{ \Illuminate\Support\Js::from($rows->map(fn ($r) => [
            'id' => $r->booking->id,
            'label' => trim(($r->booking->pet?->client?->full_name ?: 'Cliente') . ' — ' . ($r->booking->pet?->name ?: 'Mascota')),
            'hasPhone' => (bool) $r->wa_number,
            'hasEmail' => (bool) $r->booking->pet?->client?->email,
            'receivesReminders' => $r->booking->pet?->client === null || (bool) $r->booking->pet->client->receives_service_reminders,
            'alreadySentToday' => $r->already_sent_today,
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

    @include('whatsapp.bandeja._month_calendar')

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <label class="fw-semibold mb-0">Plantilla a usar:</label>
            <select class="form-select" style="max-width:360px" x-model="templateId" @change="onTemplateSelectChange()">
                <option value="">Selecciona una plantilla…</option>
                <template x-for="t in templates" :key="t.id">
                    <option :value="t.id" x-text="t.name"></option>
                </template>
                <option value="__new__">+ Crear nueva plantilla…</option>
            </select>

            <label class="fw-semibold mb-0">Enviar por:</label>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm" :class="channel === 'whatsapp' ? 'btn-success' : 'btn-outline-secondary'" @click="setChannel('whatsapp')">WhatsApp</button>
                <button type="button" class="btn btn-sm" :class="channel === 'email' ? 'btn-success' : 'btn-outline-secondary'" @click="setChannel('email')">Correo</button>
            </div>

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
                                title="No incluye las ya enviadas hoy, ni las que no tienen el dato de contacto necesario para el canal elegido"
                                @change="toggleAll($event.target.checked, eligibleIds)">
                        </th>
                        <th style="width:90px">Hora</th>
                        <th>Mascota</th>
                        <th>Cliente</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
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
                                    :disabled="!rowsById['{{ $booking->id }}']?.receivesReminders || (channel === 'whatsapp' ? !rowsById['{{ $booking->id }}']?.hasPhone : !rowsById['{{ $booking->id }}']?.hasEmail)"
                                    @if($row->already_sent_today) title="Ya enviado hoy — márcalo si quieres reenviarlo" @endif>
                            </td>
                            <td>{{ $booking->scheduled_at->format($timeFormat) }}</td>
                            <td>
                                {{ $booking->pet?->name ?? '—' }}
                                @if($client && ! $client->receives_service_reminders)
                                    <span class="badge bg-warning-subtle text-warning-emphasis" title="El cliente optó por no recibir recordatorios de servicio">No acepta recordatorios</span>
                                @endif
                            </td>
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
                            <td>
                                @if($client?->email)
                                    <span class="text-body">{{ $client->email }}</span>
                                @else
                                    <span class="text-muted">Sin correo</span>
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
                            <td colspan="8" class="text-center text-muted py-4">No hay citas SPA programadas para esta fecha.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('whatsapp._send-queue-modal')
    @include('whatsapp._create-template-modal')
</div>
@endsection
