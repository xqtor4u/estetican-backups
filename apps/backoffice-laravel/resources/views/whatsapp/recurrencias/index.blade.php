@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('whatsapp.bandeja') }}" class="btn btn-outline-secondary">Bandeja diaria</a>
        <a href="{{ route('whatsapp.plantillas.index') }}" class="btn btn-outline-secondary">Plantillas de mensaje</a>
    </x-slot:actions>
</x-page-header>

<div
    class="catalog-content-wide"
    x-data="whatsappBandeja({
        csrfToken: '{{ csrf_token() }}',
        sendUrlTemplate: '{{ route('whatsapp.recurrencias.enviar', ['key' => '__ID__']) }}',
        previewUrlTemplate: '{{ route('whatsapp.recurrencias.preview', ['key' => '__ID__']) }}',
        createTemplateUrl: '{{ route('whatsapp.plantillas.store') }}',
        templateContext: 'recurrencia',
        templateVariables: {{ \Illuminate\Support\Js::from($templateVariables) }},
        templates: {{ \Illuminate\Support\Js::from($templates->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()) }},
        rows: {{ \Illuminate\Support\Js::from($rows->map(fn ($r) => [
            'id' => $r->key,
            'label' => trim(($r->pet->client?->full_name ?: 'Cliente') . ' — ' . ($r->pet->name ?: 'Mascota') . ' (' . $r->service->name . ')'),
            'hasPhone' => (bool) $r->wa_number,
            'hasEmail' => (bool) $r->pet->client?->email,
            'receivesReminders' => $r->pet->client === null || (bool) $r->pet->client->receives_service_reminders,
            'alreadySentToday' => $r->already_sent_today,
        ])->values()) }},
    })"
>
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
                No hay plantillas activas de contexto "Recurrencias". <a href="{{ route('whatsapp.plantillas.create') }}">Crea una plantilla</a> antes de enviar recordatorios.
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
                                title="No incluye los ya enviados hoy, ni los que no tienen el dato de contacto necesario para el canal elegido"
                                @change="toggleAll($event.target.checked, eligibleIds)">
                        </th>
                        <th>Mascota</th>
                        <th>Cliente</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Servicio</th>
                        <th>Última vez</th>
                        <th style="width:110px">Vencido hace</th>
                        <th style="width:120px">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input"
                                    value="{{ $row->key }}"
                                    x-model="selected"
                                    :disabled="!rowsById['{{ $row->key }}']?.receivesReminders || (channel === 'whatsapp' ? !rowsById['{{ $row->key }}']?.hasPhone : !rowsById['{{ $row->key }}']?.hasEmail)"
                                    @if($row->already_sent_today) title="Ya enviado hoy — márcalo si quieres reenviarlo" @endif>
                            </td>
                            <td>
                                {{ $row->pet->name ?? '—' }}
                                @if($row->pet->client && ! $row->pet->client->receives_service_reminders)
                                    <span class="badge bg-warning-subtle text-warning-emphasis" title="El cliente optó por no recibir recordatorios de servicio">No acepta recordatorios</span>
                                @endif
                            </td>
                            <td>{{ $row->pet->client?->full_name ?? '—' }}</td>
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
                                @if($row->pet->client?->email)
                                    <span class="text-body">{{ $row->pet->client->email }}</span>
                                @else
                                    <span class="text-muted">Sin correo</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $row->service->name }}</td>
                            <td class="small text-muted">{{ $row->last_at->format($dateFormat) }}</td>
                            <td>
                                <span class="badge bg-warning-subtle text-warning-emphasis">{{ $row->days_overdue }}d</span>
                            </td>
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
                            <td colspan="9" class="text-center text-muted py-4">No hay mascotas con servicio recurrente vencido por el momento.</td>
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
