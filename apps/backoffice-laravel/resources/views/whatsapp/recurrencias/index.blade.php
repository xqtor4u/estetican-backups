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
        ])->values()) }},
    })"
>
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

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
                                title="No incluye los recordatorios ya enviados hoy"
                                @change="toggleAll($event.target.checked, [{{ $rows->filter(fn ($r) => $r->wa_number && ! $r->already_sent_today)->pluck('key')->map(fn ($k) => "'{$k}'")->implode(',') }}])">
                        </th>
                        <th>Mascota</th>
                        <th>Cliente</th>
                        <th>Teléfono</th>
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
                                    @disabled(! $row->wa_number)
                                    @if($row->already_sent_today) title="Ya enviado hoy — márcalo si quieres reenviarlo" @endif>
                            </td>
                            <td>{{ $row->pet->name ?? '—' }}</td>
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
                            <td colspan="8" class="text-center text-muted py-4">No hay mascotas con servicio recurrente vencido por el momento.</td>
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
