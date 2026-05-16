@php
    $screenDebugId = 'ResSho';
    use App\Support\Pages\ResourcesPage;

    $page = ResourcesPage::show($resource);
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('resources.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
        <a href="{{ route('resources.edit', $resource) }}" class="btn btn-secondary">Editar recurso</a>
        <form action="{{ route('resources.duplicate', $resource) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Duplicar recurso</button>
        </form>
        <form action="{{ route('resources.destroy', $resource) }}" method="POST" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger" data-confirm="¿Eliminar recurso? Esta acción no se puede deshacer.">Eliminar recurso</button>
        </form>
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex flex-column align-items-center mb-4 pb-3 border-bottom">
                    <form action="{{ route('resources.profile-photo.update', $resource) }}" 
                          method="POST" 
                          enctype="multipart/form-data"
                          x-data="{ 
                              submit() { 
                                  this.$nextTick(() => { 
                                      const input = this.$refs.photoInput.querySelector('input[type=file]');
                                      if (input && input.files.length > 0) {
                                          this.$el.submit();
                                      }
                                  });
                              } 
                          }">
                        @csrf
                        <div @image-cropped="submit()">
                            <x-image-upload 
                                name="photo" 
                                :value="$resource->profile_photo_path" 
                                previewShape="square" 
                                :aspectRatio="1" 
                                maxWidth="160px" 
                                label="Actualizar perfil"
                                :watermarkText="$resource->name"
                                x-ref="photoInput"
                            />
                        </div>
                        <p class="text-center small text-muted mt-2 px-3">Recorte automático 1:1</p>
                    </form>
                </div>
                
                <dl class="row mb-0">
                    <dt class="col-sm-4">Clave</dt>
                    <dd class="col-sm-8">{{ $resource->code }}</dd>

                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8">{{ $resource->name }}</dd>

                    <dt class="col-sm-4">Tipo</dt>
                    <dd class="col-sm-8">{{ strtoupper($resource->resource_type) }}</dd>

                    <dt class="col-sm-4">Sucursal</dt>
                    <dd class="col-sm-8">{{ $resource->branch?->name ?: 'Sin sucursal' }}</dd>

                    <dt class="col-sm-4">Capacidad</dt>
                    <dd class="col-sm-8">{{ $resource->capacity_label ?: 'Sin clasificación' }}</dd>

                    <dt class="col-sm-4">Estado admin.</dt>
                    <dd class="col-sm-8">{{ ucfirst($resource->administrative_status) }}</dd>

                    <dt class="col-sm-4">Estado operativo</dt>
                    <dd class="col-sm-8">{{ ucfirst($resource->operational_status) }}</dd>

                    <dt class="col-sm-4">Notas</dt>
                    <dd class="col-sm-8">{{ $resource->notes ?: 'Sin notas operativas.' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Asignaciones recientes</h2>
                    <span class="badge text-bg-light">{{ $resource->allocations_count }} total</span>
                </div>

                @if($resource->allocations->isEmpty())
                    <p class="text-body-secondary mb-0">Todavía no hay asignaciones o bloqueos registrados para este recurso.</p>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($resource->allocations as $allocation)
                            <div class="list-group-item px-0">
                                <div class="fw-semibold">{{ ucfirst($allocation->allocation_type) }}</div>
                                <div class="small text-body-secondary">
                                    {{ $allocation->starts_at?->format($datetimeFormat) }} - {{ $allocation->ends_at?->format($datetimeFormat) }}
                                    @if($allocation->pet?->name)
                                        · {{ $allocation->pet->name }}
                                    @endif
                                </div>
                                @if($allocation->notes)
                                    <div class="small mt-1">{{ $allocation->notes }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<section id="resource-photos" class="mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Fotos del recurso</h2>
            <p class="text-muted mb-0">Historial visual del activo para desgaste, accidentes, garantías y trazabilidad operativa.</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h3 class="h5">Anexar archivo a la bitácora</h3>
            <form x-data="{ photoType: 'evidencia' }" action="{{ route('resources.photos.store', $resource) }}" method="POST" enctype="multipart/form-data" class="row g-3 mt-1">
                @csrf
                <div class="col-md-5">
                    <label class="form-label fw-bold">Archivo de foto</label>
                    <x-image-upload name="photo" previewShape="square" :aspectRatio="4/3" maxWidth="200px" label="Seleccionar foto" defaultIcon="bi-camera" :watermarkText="$resource->name" />
                    <div class="form-text">Se recortará a 4:3 y se agregará la info quemada. La imagen se optimiza y se guarda en storage público.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Categoría de Foto</label>
                    <select name="photo_type" class="form-select" x-model="photoType" required>
                        <option value="evidencia">Evidencia General</option>
                        <option value="mantenimiento">Mantenimiento / Reparación</option>
                        <option value="incidencia">Incidencia / Desgaste</option>
                        <option value="perfil">Perfil Principal</option>
                    </select>
                    
                    <div class="mt-3" x-show="photoType === 'perfil'" x-transition>
                        <div class="alert alert-primary mb-0 py-2 d-flex align-items-center">
                            <i class="bi bi-info-circle-fill fs-4 me-2"></i> 
                            <small>Al guardar, reemplazará la foto principal en la cabecera del recurso.</small>
                        </div>
                    </div>
                    <!-- Hidden field to control primary flag transparently -->
                    <input type="hidden" name="is_primary" :value="photoType === 'perfil' ? '1' : '0'">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de toma</label>
                    <input type="datetime-local" name="taken_at" class="form-control">
                    <div class="form-text">Dejar en blanco para usar la fecha actual o EXIF.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripcion</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Golpe lateral, desgaste de piso, evidencia de reparación, etc."></textarea>
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
            @if($resource->photos->isEmpty())
                <p class="text-muted mb-0">No hay fotos registradas para este recurso.</p>
            @else
                @foreach($resource->photos as $photo)
                    <form id="resource-photo-form-{{ $photo->id }}" action="{{ route('resources.photos.update', [$resource, $photo]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                    </form>
                    <form id="resource-photo-delete-{{ $photo->id }}" action="{{ route('resources.photos.destroy', [$resource, $photo]) }}" method="POST">
                        @csrf
                        @method('DELETE')
                    </form>
                    <div class="border rounded p-3 mb-3 {{ $photo->is_primary ? 'border-success-subtle' : '' }}" x-data="{ editType: '{{ $photo->is_primary ? 'perfil' : $photo->photo_type }}' }">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <label class="form-label mb-0">Archivo guardado</label>
                                    @if($photo->is_primary)
                                        <span class="badge bg-success"><i class="bi bi-box-seam"></i> PERFIL PRINCIPAL</span>
                                    @elseif(strtolower($photo->photo_type) === 'mantenimiento')
                                        <span class="badge bg-info text-dark"><i class="bi bi-tools"></i> MANTENIMIENTO</span>
                                    @elseif(strtolower($photo->photo_type) === 'incidencia')
                                        <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> INCIDENCIA</span>
                                    @elseif(strtolower($photo->photo_type) === 'evidencia')
                                        <span class="badge bg-secondary"><i class="bi bi-camera"></i> EVIDENCIA GENERAL</span>
                                    @else
                                        <span class="badge bg-secondary">{{ strtoupper($photo->photo_type) }}</span>
                                    @endif
                                </div>
                                <div>
                                    <img src="{{ $photo->photo_file_url }}" alt="Foto de {{ $resource->name }}" class="img-fluid rounded border mb-2 pet-photo-preview">
                                </div>
                                <a href="{{ $photo->photo_file_url }}" target="_blank" rel="noreferrer" class="btn btn-link px-0"><i class="bi bi-box-arrow-up-right me-1"></i> Abrir foto completa</a>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Clasificación</label>
                                <select form="resource-photo-form-{{ $photo->id }}" name="photo_type" class="form-select" x-model="editType" required>
                                    <option value="evidencia">Evidencia General</option>
                                    <option value="mantenimiento">Mantenimiento / Reparación</option>
                                    <option value="incidencia">Incidencia / Desgaste</option>
                                    <option value="perfil">Perfil Principal</option>
                                </select>
                                <input form="resource-photo-form-{{ $photo->id }}" type="hidden" name="is_primary" :value="editType === 'perfil' ? '1' : '0'">
                                
                                <div class="mt-2" x-show="editType === 'perfil'" x-cloak>
                                    <div class="alert alert-success p-2 mb-0 border-0 fs-6">
                                        <i class="bi bi-check-circle-fill me-1"></i> Esta es la foto principal.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha de toma</label>
                                <input form="resource-photo-form-{{ $photo->id }}" type="datetime-local" name="taken_at" value="{{ optional($photo->taken_at)->format('Y-m-d\TH:i') }}" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripcion</label>
                                <textarea form="resource-photo-form-{{ $photo->id }}" name="description" class="form-control" rows="2">{{ $photo->description }}</textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button form="resource-photo-form-{{ $photo->id }}" type="submit" class="btn btn-outline-primary">Guardar</button>
                                <button form="resource-photo-delete-{{ $photo->id }}" type="submit" class="btn btn-outline-danger" data-confirm="¿Eliminar foto del recurso?">Eliminar</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>

<section id="resource-events" class="mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Eventos operativos</h2>
            <p class="text-muted mb-0">Incidentes, observaciones, seguimientos y evidencia del activo con referencia opcional a cliente, mascota y servicio.</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h3 class="h5">Nuevo evento</h3>
            <form action="{{ route('resources.events.store', $resource) }}" method="POST" class="row g-3 mt-1">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <input type="text" name="event_type" class="form-control" value="incidente" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="event_status" class="form-select" required>
                        <option value="open">Abierto</option>
                        <option value="in_progress">En seguimiento</option>
                        <option value="resolved">Resuelto</option>
                        <option value="closed">Cerrado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Severidad</label>
                    <select name="severity" class="form-select" required>
                        <option value="low">Baja</option>
                        <option value="medium" selected>Media</option>
                        <option value="high">Alta</option>
                        <option value="critical">Critica</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Titulo</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha ocurrencia</label>
                    <input type="datetime-local" name="occurred_at" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha deteccion</label>
                    <input type="datetime-local" name="detected_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Detectado por</label>
                    <select name="detected_by_user_id" class="form-select">
                        <option value="">Sin asignar</option>
                        @foreach($eventFormUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Responsable</label>
                    <select name="responsible_user_id" class="form-select">
                        <option value="">Sin asignar</option>
                        @foreach($eventFormUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cliente</label>
                    <select name="client_id" class="form-select">
                        <option value="">Sin cliente</option>
                        @foreach($eventFormClients as $client)
                            <option value="{{ $client->id }}">{{ trim($client->first_name . ' ' . $client->last_name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mascota</label>
                    <select name="pet_id" class="form-select">
                        <option value="">Sin mascota</option>
                        @foreach($eventFormPets as $pet)
                            <option value="{{ $pet->id }}">{{ $pet->name }}@if($pet->client) · {{ trim($pet->client->first_name . ' ' . $pet->client->last_name) }}@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Servicio</label>
                    <select name="service_id" class="form-select">
                        <option value="">Sin servicio</option>
                        @foreach($eventFormServices as $service)
                            <option value="{{ $service->id }}">{{ $service->code ? $service->code . ' · ' : '' }}{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripcion</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Detalle del incidente, observacion o seguimiento inicial."></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Crear evento</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if($resource->events->isEmpty())
                <p class="text-muted mb-0">No hay eventos operativos registrados para este recurso.</p>
            @else
                <div class="list-group list-group-flush">
                    @foreach($resource->events as $event)
                        <a href="{{ route('resources.events.show', [$resource, $event]) }}" class="list-group-item list-group-item-action px-0">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $event->title }}</div>
                                    <div class="small text-body-secondary">
                                        {{ strtoupper($event->event_type) }} · {{ strtoupper($event->event_status) }} · {{ strtoupper($event->severity) }}
                                    </div>
                                    <div class="small text-body-secondary mt-1">
                                        Detectado {{ optional($event->detected_at)->format($datetimeFormat) ?: 'sin fecha' }}
                                        @if($event->pet?->name)
                                            · {{ $event->pet->name }}
                                        @endif
                                        @if($event->service?->name)
                                            · {{ $event->service->name }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-end small text-body-secondary">
                                    <div>{{ $event->updates_count }} seguimientos</div>
                                    <div>{{ $event->photos_count }} fotos</div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
@endsection