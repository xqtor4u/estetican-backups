@php
    $screenDebugId = 'PetSho';

    $isRootView = $isRootView ?? false;
    $returnViewMode = $returnViewMode ?? 'blocks';
    $page = \App\Support\Pages\PetsPage::show($pet, $client, $isRootView, $returnViewMode);
    $breadcrumbs = $page['breadcrumbs'];
    $clinicalModuleEnabled = $clinicalModuleEnabled ?? (bool) app(\App\Support\SystemSettings\SystemSettings::class)->all()['clinical_module_enabled'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        @if($isRootView)
            <a href="{{ route('pets.index', ['view' => $returnViewMode]) }}" class="btn btn-outline-secondary">Volver al listado</a>
        @endif
        <a href="{{ $isRootView ? route('pets.bookings.create', ['pet' => $pet, 'return_view_mode' => $returnViewMode]) : route('clients.pets.bookings.create', [$client, $pet]) }}" class="btn btn-outline-dark catalog-action-upcoming">Programar servicio</a>
        @if($clinicalModuleEnabled && auth()->user()?->can('ver clinico'))
            <a href="{{ route('clinical.pets.show', $pet) }}" class="btn btn-outline-info">Ver expediente clínico</a>
        @endif
        <a href="{{ route('clients.show', $client) }}" class="btn btn-outline-secondary">Ver cliente</a>
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-secondary">Editar cliente</a>
        <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#modalChangeOwner">Cambiar dueño</button>
        <form action="{{ $isRootView ? route('pets.destroy', $pet) : route('clients.pets.destroy', [$client, $pet]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar permanentemente a esta mascota? Esta acción no se puede deshacer.')">
            @csrf
            @method('DELETE')
            @if($isRootView)
                <input type="hidden" name="view" value="{{ $returnViewMode }}">
            @endif
            <button type="submit" class="btn btn-outline-danger">Eliminar Mascota</button>
        </form>
    </x-slot:actions>
</x-page-header>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <strong>No se pudo guardar.</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<section id="core-profile" class="mb-4">
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3 flex-wrap">
                <div>
                    <h2 class="h4 mb-1">Ficha base de mascota</h2>
                    <p class="text-muted mb-0">El nombre y el resto del core de mascota se editan aqui; no dependen del indice relacional del cliente.</p>
                </div>
                <span class="badge text-bg-light">Core editable</span>
            </div>

            <div class="row">
                <div class="col-md-3 border-end pe-md-4 mb-4 mb-md-0 d-flex flex-column align-items-center justify-content-center">
                    <h3 class="h5 mb-3 text-center text-primary fw-bold">Foto de Perfil</h3>
                    <form id="pet-profile-photo-form"
                          action="{{ $isRootView ? route('pets.profile-photo.update', $pet) : route('clients.pets.profile-photo.update', [$client, $pet]) }}" 
                          method="POST" 
                          enctype="multipart/form-data">
                        @csrf
                        <div>
                            <x-image-upload 
                                name="photo" 
                                :value="$pet->profile_photo_path" 
                                previewShape="circle" 
                                :aspectRatio="1" 
                                maxWidth="160px" 
                                label="Actualizar foto"
                                :watermarkText="$pet->name"
                                autoSubmitFormId="pet-profile-photo-form"
                            />
                            <button type="submit" style="display: none;"></button>
                        </div>
                        <p class="text-center small text-muted mt-2 px-3">Recorte automático 1:1</p>
                    </form>
                </div>
                
                <div class="col-md-9 ps-md-4">
                    <form action="{{ $isRootView ? route('pets.update', $pet) : route('clients.pets.update', [$client, $pet]) }}" method="POST" class="row g-3">
                        @csrf
                        @method('PUT')
                @if($isRootView)
                    <input type="hidden" name="return_view_mode" value="{{ $returnViewMode }}">
                @endif
                <div class="col-md-4">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" value="{{ old('name', $pet->name) }}" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Especie</label>
                    <input type="text" name="species" value="{{ old('species', $pet->species) }}" class="form-control" placeholder="Perro, Gato, Pajaro...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Raza</label>
                    <input type="text" name="breed" value="{{ old('breed', $pet->breed) }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de nacimiento</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', optional($pet->birth_date)->format('Y-m-d')) }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de deceso</label>
                    <input type="date" name="death_date" value="{{ old('death_date', optional($pet->death_date)->format('Y-m-d')) }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Numero de chip</label>
                    <input type="text" name="microchip_code" value="{{ old('microchip_code', $pet->microchip_code) }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Numero de tatuaje</label>
                    <input type="text" name="tattoo_code" value="{{ old('tattoo_code', $pet->tattoo_code) }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sexo</label>
                    <select name="sex" class="form-select">
                        <option value="" @selected(old('sex', $pet->sex) === null)>Seleccionar</option>
                        <option value="male" @selected(old('sex', $pet->sex) === 'male')>Macho</option>
                        <option value="female" @selected(old('sex', $pet->sex) === 'female')>Hembra</option>
                        <option value="unknown" @selected(old('sex', $pet->sex) === 'unknown')>No definido</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tamano</label>
                    <select name="size" class="form-select">
                        <option value="" @selected(old('size', $pet->size) === null)>Seleccionar</option>
                        <option value="mini" @selected(old('size', $pet->size) === 'mini')>Mini</option>
                        <option value="small" @selected(old('size', $pet->size) === 'small')>Pequeno</option>
                        <option value="medium" @selected(old('size', $pet->size) === 'medium')>Mediano</option>
                        <option value="large" @selected(old('size', $pet->size) === 'large')>Grande</option>
                        <option value="giant" @selected(old('size', $pet->size) === 'giant')>Gigante</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Color</label>
                    <input type="text" name="coat_color" value="{{ old('coat_color', $pet->coat_color) }}" class="form-control">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="hidden" name="is_sterilized" value="0">
                        <input class="form-check-input" type="checkbox" name="is_sterilized" value="1" id="pet-is-sterilized" @checked(old('is_sterilized', $pet->is_sterilized))>
                        <label class="form-check-label" for="pet-is-sterilized">Esterilizada</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $pet->notes) }}</textarea>
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">Guardar ficha base</button>
                    @if($isRootView)
                        <a href="{{ route('pets.index', ['view' => $returnViewMode]) }}" class="btn btn-outline-secondary">Volver al listado</a>
                    @else
                        <a href="{{ route('clients.edit', $client) }}" class="btn btn-outline-secondary">Editar cliente completo</a>
                    @endif
                </div>
                </form>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Cambiar entre mascotas del cliente</h5>
        <div class="d-flex flex-wrap gap-2 mt-3">
            @foreach($client->pets as $clientPet)
                <a
                    href="{{ $isRootView ? route('pets.show', ['pet' => $clientPet, 'view' => $returnViewMode]) : route('clients.pets.show', [$client, $clientPet]) }}"
                    class="btn {{ $clientPet->is($pet) ? 'btn-primary' : 'btn-outline-primary' }} {{ $clientPet->death_date ? 'opacity-75' : '' }}"
                >
                    {{ $clientPet->name }}
                    @if($clientPet->species_label)
                        · {{ $clientPet->species_label }}
                    @endif
                    @if($clientPet->death_date)
                        · Fallecida
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>

<div class="card mb-4 {{ $pet->death_date ? 'border-secondary' : '' }}">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <h4 class="card-title mb-1">{{ $pet->name }}</h4>
                <p class="text-muted mb-0">
                    Cliente: {{ $client->first_name }} {{ $client->last_name }}
                    @if($pet->species_label)
                        · {{ $pet->species_label }}
                    @endif
                    @if($pet->breed)
                        · {{ $pet->breed }}
                    @endif
                </p>
            </div>
            <div class="text-md-end">
                <span class="badge text-bg-primary">{{ $pet->medicalAlerts->count() }} alertas</span>
                <span class="badge text-bg-dark">{{ $pet->photos->count() }} fotos</span>
                @if($pet->death_date)
                    <span class="badge text-bg-secondary">Fallecida</span>
                @endif
            </div>
        </div>

        <div class="catalog-actions-cluster mt-3">
            <a href="{{ $isRootView ? route('pets.bookings.create', ['pet' => $pet, 'return_view_mode' => $returnViewMode]) : route('clients.pets.bookings.create', [$client, $pet]) }}" class="btn btn-sm btn-outline-dark catalog-action-upcoming">Programar servicio</a>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <strong>Edad</strong>
                <div>{{ $pet->age_description ?: 'Sin dato' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Identificacion</strong>
                <div>Chip: {{ $pet->microchip_code ?: 'Sin dato' }}</div>
                <div>Tatuaje: {{ $pet->tattoo_code ?: 'Sin dato' }}</div>
            </div>
            <div class="col-md-4">
                <strong>Perfil</strong>
                <div>Sexo: {{ match($pet->sex) { 'male' => 'Macho', 'female' => 'Hembra', 'unknown' => 'No definido', default => 'Sin dato' } }}</div>
                <div>Tamano: {{ match($pet->size) { 'mini' => 'Mini', 'small' => 'Pequeno', 'medium' => 'Mediano', 'large' => 'Grande', 'giant' => 'Gigante', default => 'Sin dato' } }}</div>
                <div>{{ $pet->is_sterilized ? 'Esterilizada' : 'No esterilizada' }}</div>
            </div>
            <div class="col-12">
                <strong>Notas</strong>
                <div>{{ $pet->notes ?: 'Sin notas registradas' }}</div>
            </div>
        </div>
    </div>
</div>

<section id="upcoming-bookings" class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="h4 mb-1">Servicios programados</h2>
            <p class="text-muted mb-0">Lectura rápida de próximas sesiones para esta mascota dentro del módulo de Agenda.</p>
        </div>
        <a href="{{ route('agenda.index') }}" class="btn btn-outline-secondary">Abrir agenda</a>
    </div>

    <x-list-table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Servicios</th>
                <th>Estado</th>
                <th>Total estimado</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pet->spaBookings as $booking)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $booking->scheduled_at?->format($datetimeFormat) }}</div>
                        <div class="text-body-secondary small">{{ $booking->scheduled_at?->diffForHumans() }}</div>
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
                        <div class="catalog-stat__hint">estimado</div>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('agenda.show', $booking) }}" class="btn btn-sm btn-outline-dark">Detalle</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center py-4 text-body-secondary">Aún no hay servicios programados para esta mascota.</td>
                </tr>
            @endforelse
        </tbody>
    </x-list-table>
</section>

@if($clinicalModuleEnabled && $pet->vaccinations->isNotEmpty())
<section id="vaccination-summary" class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Vacunación</h2>
            <p class="text-muted mb-0">Resumen informativo desde el módulo de Veterinaria.</p>
        </div>
        @can('ver clinico')
            <a href="{{ route('clinical.pets.show', $pet) }}" class="btn btn-sm btn-outline-info">Ver expediente clínico</a>
        @endcan
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-sm mb-0">
                <thead><tr><th>Vacuna</th><th>Aplicada</th><th>Vigente hasta</th><th>Estado</th></tr></thead>
                <tbody>
                    @foreach($pet->vaccinations as $vaccination)
                        <tr>
                            <td>{{ $vaccination->service?->name ?? $vaccination->vaccine_name }}</td>
                            <td>{{ $vaccination->applied_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $vaccination->expires_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                @if($vaccination->isExpired())
                                    <span class="badge text-bg-danger">Vencida</span>
                                @else
                                    <span class="badge text-bg-success">Vigente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endif

<section id="medical-alerts" class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Alertas medicas</h2>
            <p class="text-muted mb-0">CRUD directo sobre la tabla pet_medical_alerts.</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h3 class="h5">Nueva alerta</h3>
            <form action="{{ route('clients.pets.medical-alerts.store', [$client, $pet]) }}" method="POST" class="row g-3 mt-1">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Categoria</label>
                    <input type="text" name="category" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Severidad</label>
                    <select name="severity" class="form-control">
                        <option value="">Sin definir</option>
                        <option value="low">Baja</option>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                        <option value="critical">Critica</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="alert-active-create" checked>
                        <label class="form-check-label" for="alert-active-create">Activa</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripcion</label>
                    <textarea name="description" class="form-control" rows="2" required></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Crear alerta</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if($pet->medicalAlerts->isEmpty())
                <p class="text-muted mb-0">No hay alertas medicas registradas para esta mascota.</p>
            @else
                @foreach($pet->medicalAlerts as $medicalAlert)
                    <form id="medical-alert-form-{{ $medicalAlert->id }}" action="{{ route('clients.pets.medical-alerts.update', [$client, $pet, $medicalAlert]) }}" method="POST">
                        @csrf
                        @method('PUT')
                    </form>
                    <form id="medical-alert-delete-{{ $medicalAlert->id }}" action="{{ route('clients.pets.medical-alerts.destroy', [$client, $pet, $medicalAlert]) }}" method="POST">
                        @csrf
                        @method('DELETE')
                    </form>
                    <div class="border rounded p-3 mb-3 {{ $medicalAlert->is_active ? '' : 'bg-light' }}">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Categoria</label>
                                <input form="medical-alert-form-{{ $medicalAlert->id }}" type="text" name="category" value="{{ $medicalAlert->category }}" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Severidad</label>
                                <select form="medical-alert-form-{{ $medicalAlert->id }}" name="severity" class="form-control">
                                    <option value="" @selected(!$medicalAlert->severity)>Sin definir</option>
                                    <option value="low" @selected($medicalAlert->severity === 'low')>Baja</option>
                                    <option value="medium" @selected($medicalAlert->severity === 'medium')>Media</option>
                                    <option value="high" @selected($medicalAlert->severity === 'high')>Alta</option>
                                    <option value="critical" @selected($medicalAlert->severity === 'critical')>Critica</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input form="medical-alert-form-{{ $medicalAlert->id }}" type="hidden" name="is_active" value="0">
                                    <input form="medical-alert-form-{{ $medicalAlert->id }}" class="form-check-input" type="checkbox" name="is_active" value="1" id="alert-active-{{ $medicalAlert->id }}" @checked($medicalAlert->is_active)>
                                    <label class="form-check-label" for="alert-active-{{ $medicalAlert->id }}">Activa</label>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end justify-content-md-end gap-2">
                                <button form="medical-alert-form-{{ $medicalAlert->id }}" type="submit" class="btn btn-outline-primary">Guardar</button>
                                <button form="medical-alert-delete-{{ $medicalAlert->id }}" type="submit" class="btn btn-outline-danger" data-confirm="¿Eliminar alerta medica?">Eliminar</button>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripcion</label>
                                <textarea form="medical-alert-form-{{ $medicalAlert->id }}" name="description" class="form-control" rows="2" required>{{ $medicalAlert->description }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notas</label>
                                <textarea form="medical-alert-form-{{ $medicalAlert->id }}" name="notes" class="form-control" rows="2">{{ $medicalAlert->notes }}</textarea>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>

<section id="pet-photos">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Fotos de mascota</h2>
            <p class="text-muted mb-0">CRUD directo sobre la tabla pet_photos.</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h3 class="h5">Anexar archivo a la bitácora</h3>
            <form x-data="{ photoType: 'ingreso' }" action="{{ route('clients.pets.photos.store', [$client, $pet]) }}" method="POST" enctype="multipart/form-data" class="row g-3 mt-1">
                @csrf
                <div class="col-md-5">
                    <label class="form-label fw-bold">Archivo de foto (Obligatorio)</label>
                    <x-image-upload name="photo" previewShape="square" :aspectRatio="4/3" maxWidth="200px" label="Seleccionar foto" defaultIcon="bi-camera" :watermarkText="$pet->name" />
                    <div class="form-text mt-2">La imagen se recortará a proporción 4:3 para mantener estandarización en los perfiles de mascotas y se optimizará automáticamente.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Categoría de Foto</label>
                    <select name="photo_type" class="form-select" x-model="photoType" required>
                        <option value="ingreso">Ingreso (Estado Inicial)</option>
                        <option value="incidencia">Incidencia Médica/Comportamiento</option>
                        <option value="resultado">Resultado Final (Después)</option>
                        <option value="perfil">Perfil</option>
                    </select>
                    
                    <div class="mt-3" x-show="photoType === 'perfil'" x-transition>
                        <div class="alert alert-primary mb-0 py-2 d-flex align-items-center">
                            <i class="bi bi-info-circle-fill fs-4 me-2"></i> 
                            <small>Al guardar, reemplazará la foto de perfil en la ficha superior.</small>
                        </div>
                    </div>
                    <!-- Hidden field to control primary flag transparently -->
                    <input type="hidden" name="is_primary" :value="photoType === 'perfil' ? '1' : '0'">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de toma</label>
                    <input type="datetime-local" name="taken_at" class="form-control" data-photo-taken-at>
                    <div class="form-text" data-photo-exif-status>Si la dejas vacia, el sistema intentara leerla desde los metadatos EXIF de la foto.</div>
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
            @if($pet->photos->isEmpty())
                <p class="text-muted mb-0">No hay fotos registradas para esta mascota.</p>
            @else
                @foreach($pet->photos as $photo)
                    <form id="pet-photo-form-{{ $photo->id }}" action="{{ route('clients.pets.photos.update', [$client, $pet, $photo]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                    </form>
                    <form id="pet-photo-delete-{{ $photo->id }}" action="{{ route('clients.pets.photos.destroy', [$client, $pet, $photo]) }}" method="POST">
                        @csrf
                        @method('DELETE')
                    </form>
                    <div class="border rounded p-3 mb-3 {{ $photo->is_primary ? 'border-success-subtle' : '' }}" x-data="{ editType: '{{ $photo->is_primary ? 'perfil' : $photo->photo_type }}' }">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <label class="form-label mb-0">Archivo guardado</label>
                                    @if($photo->is_primary)
                                        <span class="badge bg-success"><i class="bi bi-person-badge"></i> PERFIL ACTUAL</span>
                                    @elseif(strtolower($photo->photo_type) === 'ingreso')
                                        <span class="badge bg-info text-dark"><i class="bi bi-box-arrow-in-right"></i> INGRESO</span>
                                    @elseif(strtolower($photo->photo_type) === 'incidencia')
                                        <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> INCIDENCIA</span>
                                    @elseif(strtolower($photo->photo_type) === 'resultado')
                                        <span class="badge bg-primary"><i class="bi bi-check2-circle"></i> RESULTADO</span>
                                    @else
                                        <span class="badge bg-secondary">{{ strtoupper($photo->photo_type) }}</span>
                                    @endif
                                </div>
                                <div>
                                    <img src="{{ $photo->photo_file_url }}" alt="Foto de {{ $pet->name }}" class="img-fluid rounded border mb-2 pet-photo-preview">
                                </div>
                                <a href="{{ $photo->photo_file_url }}" target="_blank" rel="noreferrer" class="btn btn-link px-0"><i class="bi bi-box-arrow-up-right me-1"></i> Abrir foto completa</a>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Clasificación</label>
                                <select form="pet-photo-form-{{ $photo->id }}" name="photo_type" class="form-select" x-model="editType" required>
                                    <option value="ingreso">Ingreso (Estado Inicial)</option>
                                    <option value="incidencia">Incidencia Médica/Comportamiento</option>
                                    <option value="resultado">Resultado Final (Después)</option>
                                    <option value="perfil">Perfil</option>
                                </select>
                                <input form="pet-photo-form-{{ $photo->id }}" type="hidden" name="is_primary" :value="editType === 'perfil' ? '1' : '0'">
                                
                                <div class="mt-2" x-show="editType === 'perfil'" x-cloak>
                                    <div class="alert alert-success p-2 mb-0 border-0 fs-6">
                                        <i class="bi bi-check-circle-fill me-1"></i> Esta es la foto principal.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha de toma</label>
                                <input form="pet-photo-form-{{ $photo->id }}" type="datetime-local" name="taken_at" value="{{ optional($photo->taken_at)->format('Y-m-d\TH:i') }}" class="form-control" data-photo-taken-at>
                                <div class="form-text" data-photo-exif-status>Si reemplazas la foto y dejas este campo vacio, se intentara usar la fecha EXIF.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripcion</label>
                                <textarea form="pet-photo-form-{{ $photo->id }}" name="description" class="form-control" rows="2">{{ $photo->description }}</textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button form="pet-photo-form-{{ $photo->id }}" type="submit" class="btn btn-outline-primary">Guardar</button>
                                <button form="pet-photo-delete-{{ $photo->id }}" type="submit" class="btn btn-outline-danger" data-confirm="¿Eliminar foto?">Eliminar</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>

{{-- Modal: Cambiar dueño --}}
<div class="modal fade" id="modalChangeOwner" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div
            class="modal-content border-0 shadow-lg"
            x-data="{
                search: '',
                selected: {{ $client->id }},
                clients: {{ \Illuminate\Support\Js::from($allClients->map(fn ($c) => [
                    'id' => $c->id,
                    'label' => trim($c->first_name . ' ' . $c->last_name) . ($c->email ? ' — ' . $c->email : ''),
                ])) }},
                get filtered() {
                    const q = this.search.trim().toLowerCase();
                    if (!q) return this.clients;
                    return this.clients.filter(c => c.label.toLowerCase().includes(q));
                }
            }"
        >
            <form action="{{ route('pets.owner.update', $pet) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Cambiar dueño de {{ $pet->name }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small">Dueño actual: <strong>{{ $client->first_name }} {{ $client->last_name }}</strong>. El historial de citas y presupuestos pasados no cambia — solo se reasigna la mascota hacia adelante.</p>
                    <input type="text" class="form-control mb-3" placeholder="Buscar cliente…" x-model="search">
                    <div class="list-group" style="max-height:280px; overflow-y:auto">
                        <template x-for="c in filtered" :key="c.id">
                            <label class="list-group-item d-flex align-items-center gap-2" :class="{ 'active': selected === c.id }">
                                <input type="radio" name="client_id" class="form-check-input mt-0" :value="c.id" x-model.number="selected">
                                <span x-text="c.label"></span>
                            </label>
                        </template>
                        <p class="text-muted small mb-0 mt-2" x-show="filtered.length === 0">Sin resultados.</p>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark" :disabled="selected === {{ $client->id }}">Guardar nuevo dueño</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection