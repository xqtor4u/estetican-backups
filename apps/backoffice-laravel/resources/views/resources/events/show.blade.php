@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Recursos · evento operativo"
    :title="$event->title"
    :subtitle="'Seguimiento del evento ' . strtoupper($event->event_type) . ' para ' . $resource->name"
>
    <x-slot:actions>
        <a href="{{ route('resources.show', $resource) }}#resource-events" class="btn btn-outline-secondary">Volver al recurso</a>
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Tipo</dt>
                    <dd class="col-sm-8">{{ strtoupper($event->event_type) }}</dd>

                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">{{ strtoupper($event->event_status) }}</dd>

                    <dt class="col-sm-4">Severidad</dt>
                    <dd class="col-sm-8">{{ strtoupper($event->severity) }}</dd>

                    <dt class="col-sm-4">Detectado</dt>
                    <dd class="col-sm-8">{{ optional($event->detected_at)->format('d/m/Y ' . (config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A')) ?: 'Sin fecha' }}</dd>

                    <dt class="col-sm-4">Ocurrido</dt>
                    <dd class="col-sm-8">{{ optional($event->occurred_at)->format('d/m/Y ' . (config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A')) ?: 'Sin fecha' }}</dd>

                    <dt class="col-sm-4">Detector</dt>
                    <dd class="col-sm-8">{{ $event->detectedBy?->name ?: 'Sin asignar' }}</dd>

                    <dt class="col-sm-4">Responsable</dt>
                    <dd class="col-sm-8">{{ $event->responsibleUser?->name ?: 'Sin asignar' }}</dd>

                    <dt class="col-sm-4">Cliente</dt>
                    <dd class="col-sm-8">{{ $event->client ? trim($event->client->first_name . ' ' . $event->client->last_name) : 'Sin cliente' }}</dd>

                    <dt class="col-sm-4">Mascota</dt>
                    <dd class="col-sm-8">{{ $event->pet?->name ?: 'Sin mascota' }}</dd>

                    <dt class="col-sm-4">Servicio</dt>
                    <dd class="col-sm-8">{{ $event->service?->name ?: 'Sin servicio' }}</dd>

                    <dt class="col-sm-4">Descripcion</dt>
                    <dd class="col-sm-8">{{ $event->description ?: 'Sin descripcion adicional.' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100" id="event-updates">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Seguimiento</h2>
                    <span class="badge text-bg-light">{{ $event->updates->count() }} registros</span>
                </div>

                <form action="{{ route('resources.events.updates.store', [$resource, $event]) }}" method="POST" class="row g-3 mb-4">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Tipo de seguimiento</label>
                        <input type="text" name="update_type" class="form-control" value="seguimiento" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nuevo estado</label>
                        <select name="to_status" class="form-select">
                            <option value="">Sin cambio</option>
                            <option value="open">Abierto</option>
                            <option value="in_progress">En seguimiento</option>
                            <option value="resolved">Resuelto</option>
                            <option value="closed">Cerrado</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Diagnostico, accion correctiva, verificacion o cierre."></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Registrar seguimiento</button>
                    </div>
                </form>

                @if($event->updates->isEmpty())
                    <p class="text-muted mb-0">Todavia no hay seguimiento registrado para este evento.</p>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($event->updates as $update)
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold">{{ $update->update_type }}</div>
                                        <div class="small text-body-secondary">
                                            {{ $update->created_at?->format('d/m/Y ' . (config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A')) }}
                                            @if($update->createdBy?->name)
                                                · {{ $update->createdBy->name }}
                                            @endif
                                            @if($update->from_status || $update->to_status)
                                                · {{ $update->from_status ?: 'sin cambio' }} → {{ $update->to_status ?: 'sin cambio' }}
                                            @endif
                                        </div>
                                        @if($update->notes)
                                            <div class="mt-1">{{ $update->notes }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<section id="event-photos" class="mt-4">
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5 mb-3">Nueva foto del evento</h2>
            <form action="{{ route('resources.events.photos.store', [$resource, $event]) }}" method="POST" enctype="multipart/form-data" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label fw-bold">Archivo (Obligatorio)</label>
                    <x-image-upload name="photo" previewShape="square" :aspectRatio="4/3" maxWidth="200px" label="Seleccionar foto" defaultIcon="bi-camera" :watermarkText="$event->pet ? $event->pet->name : ($event->client ? $event->client->first_name : $resource->name)" />
                    <div class="form-text mt-2">Se recortará a 4:3 y se agregará la info quemada.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <input type="text" name="photo_type" class="form-control" value="evidencia" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de toma</label>
                    <input type="datetime-local" name="taken_at" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="is_primary" value="0">
                        <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="event-photo-primary-create">
                        <label class="form-check-label" for="event-photo-primary-create">Principal</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Etapa de seguimiento</label>
                    <select name="resource_event_update_id" class="form-select">
                        <option value="">Sin etapa especifica</option>
                        @foreach($updateOptions as $updateOption)
                            <option value="{{ $updateOption->id }}">{{ $updateOption->update_type }} · {{ $updateOption->created_at?->format('d/m/Y ' . (config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A')) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripcion</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i> Subir y Guardar Foto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Evidencia fotografica</h2>
                <span class="badge text-bg-light">{{ $event->photos->count() }} fotos</span>
            </div>

            @if($event->photos->isEmpty())
                <p class="text-muted mb-0">No hay fotos registradas para este evento.</p>
            @else
                @foreach($event->photos as $photo)
                    <form id="event-photo-form-{{ $photo->id }}" action="{{ route('resources.events.photos.update', [$resource, $event, $photo]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                    </form>
                    <form id="event-photo-delete-{{ $photo->id }}" action="{{ route('resources.events.photos.destroy', [$resource, $event, $photo]) }}" method="POST">
                        @csrf
                        @method('DELETE')
                    </form>
                    <div class="border rounded p-3 mb-3 {{ $photo->is_primary ? 'border-primary' : '' }}">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <img src="{{ $photo->photo_file_url }}" alt="Evidencia del evento {{ $event->title }}" class="img-fluid rounded border mb-2 pet-photo-preview">
                                <a href="{{ $photo->photo_file_url }}" target="_blank" rel="noreferrer" class="btn btn-link px-0">Abrir foto</a>
                                <div class="mt-2">
                                    <label class="form-label text-primary fw-bold">Reemplazar archivo</label>
                                    <x-image-upload name="photo" previewShape="square" :aspectRatio="4/3" maxWidth="150px" label="Subir nueva evidencia" defaultIcon="bi-camera" formId="event-photo-form-{{ $photo->id }}" :watermarkText="$event->pet ? $event->pet->name : ($event->client ? $event->client->first_name : $resource->name)" />
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo</label>
                                <input form="event-photo-form-{{ $photo->id }}" type="text" name="photo_type" value="{{ $photo->photo_type }}" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fecha de toma</label>
                                <input form="event-photo-form-{{ $photo->id }}" type="datetime-local" name="taken_at" value="{{ optional($photo->taken_at)->format('Y-m-d\TH:i') }}" class="form-control">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input form="event-photo-form-{{ $photo->id }}" type="hidden" name="is_primary" value="0">
                                    <input form="event-photo-form-{{ $photo->id }}" class="form-check-input" type="checkbox" name="is_primary" value="1" id="event-photo-primary-{{ $photo->id }}" @checked($photo->is_primary)>
                                    <label class="form-check-label" for="event-photo-primary-{{ $photo->id }}">Principal</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Etapa</label>
                                <select form="event-photo-form-{{ $photo->id }}" name="resource_event_update_id" class="form-select">
                                    <option value="">Sin etapa especifica</option>
                                    @foreach($updateOptions as $updateOption)
                                        <option value="{{ $updateOption->id }}" @selected($photo->resource_event_update_id === $updateOption->id)>{{ $updateOption->update_type }} · {{ $updateOption->created_at?->format('d/m/Y ' . (config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A')) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripcion</label>
                                <textarea form="event-photo-form-{{ $photo->id }}" name="description" class="form-control" rows="2">{{ $photo->description }}</textarea>
                            </div>
                            @if($photo->eventUpdate)
                                <div class="col-12 small text-body-secondary">Ligada a etapa {{ $photo->eventUpdate->update_type }} del {{ $photo->eventUpdate->created_at?->format('d/m/Y ' . (config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A')) }}</div>
                            @endif
                            <div class="col-12 d-flex gap-2">
                                <button form="event-photo-form-{{ $photo->id }}" type="submit" class="btn btn-outline-primary">Guardar</button>
                                <button form="event-photo-delete-{{ $photo->id }}" type="submit" class="btn btn-outline-danger" data-confirm="¿Eliminar foto del evento?">Eliminar</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>
@endsection