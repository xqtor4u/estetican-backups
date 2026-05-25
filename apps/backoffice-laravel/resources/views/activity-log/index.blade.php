@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Administración"
    title="Bitácora de Actividad"
    subtitle="Registro de operaciones realizadas por los usuarios del sistema"
/>

{{-- Filtros --}}
<div class="card mt-4 mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('activity-log.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Módulo</label>
                <select name="log" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($logNames as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['log'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Evento</label>
                <select name="event" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="created" @selected(($filters['event'] ?? '') === 'created')>Creado</option>
                    <option value="updated" @selected(($filters['event'] ?? '') === 'updated')>Modificado</option>
                    <option value="deleted" @selected(($filters['event'] ?? '') === 'deleted')>Eliminado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Usuario</label>
                <select name="causer" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(($filters['causer'] ?? '') == $user->id)>
                            {{ $user->name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Fecha</label>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="{{ $filters['date'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtrar</button>
                <a href="{{ route('activity-log.index') }}" class="btn btn-outline-secondary btn-sm">✕</a>
            </div>
        </form>
    </div>
</div>

{{-- Tabla --}}
<x-list-table>
    <thead>
        <tr>
            <th style="width:160px">Fecha</th>
            <th style="width:130px">Módulo</th>
            <th style="width:100px">Evento</th>
            <th>Descripción</th>
            <th style="width:180px">Usuario</th>
            <th style="width:90px">Registro</th>
        </tr>
    </thead>
    <tbody>
        @forelse($activities as $activity)
        @php
            $eventColors = [
                'created' => 'success',
                'updated' => 'primary',
                'deleted' => 'danger',
            ];
            $eventLabels = [
                'created' => 'Creado',
                'updated' => 'Modificado',
                'deleted' => 'Eliminado',
            ];
            $color = $eventColors[$activity->event] ?? 'secondary';
            $label = $eventLabels[$activity->event] ?? ucfirst($activity->event ?? '–');
            $logLabel = $logNames[$activity->log_name] ?? $activity->log_name;
            $changes = $activity->properties->get('attributes', []);
            $old = $activity->properties->get('old', []);
        @endphp
        <tr>
            <td class="text-muted small">
                {{ $activity->created_at->format('d/m/Y H:i:s') }}
            </td>
            <td>
                <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">
                    {{ $logLabel }}
                </span>
            </td>
            <td>
                <span class="badge bg-{{ $color }}-subtle text-{{ $color }}-emphasis fw-semibold">
                    {{ $label }}
                </span>
            </td>
            <td>
                @if($activity->event === 'updated' && count($changes))
                    <div class="small">
                        @foreach($changes as $field => $newVal)
                        <div class="d-flex gap-2 align-items-baseline">
                            <span class="text-muted" style="min-width:140px;font-size:.75rem">{{ $field }}</span>
                            @if(isset($old[$field]))
                                <span class="text-danger text-decoration-line-through" style="font-size:.8rem">{{ is_array($old[$field]) ? json_encode($old[$field]) : $old[$field] }}</span>
                                <span class="text-muted">→</span>
                            @endif
                            <span class="text-success" style="font-size:.8rem">{{ is_array($newVal) ? json_encode($newVal) : $newVal }}</span>
                        </div>
                        @endforeach
                    </div>
                @elseif($activity->event === 'created' && count($changes))
                    <div class="small text-muted">
                        @foreach($changes as $field => $val)
                            <span class="me-2"><strong>{{ $field }}:</strong> {{ is_array($val) ? json_encode($val) : $val }}</span>
                        @endforeach
                    </div>
                @elseif($activity->event === 'deleted')
                    <span class="text-muted small">Registro eliminado</span>
                @else
                    <span class="text-muted small">–</span>
                @endif
            </td>
            <td class="small">
                @if($activity->causer)
                    <div class="fw-semibold">{{ $activity->causer->name }}</div>
                    <div class="text-muted" style="font-size:.75rem">{{ $activity->causer->email }}</div>
                @else
                    <span class="text-muted">Sistema</span>
                @endif
            </td>
            <td class="text-muted small">
                #{{ $activity->subject_id }}
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="6" class="text-center text-muted py-4">No hay actividad registrada con los filtros seleccionados.</td>
        </tr>
        @endforelse
    </tbody>
</x-list-table>

<div class="mt-3">
    {{ $activities->links() }}
</div>
@endsection
