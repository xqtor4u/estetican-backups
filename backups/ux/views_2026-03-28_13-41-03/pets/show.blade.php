@extends('layouts.app')

@php
    use App\Support\Pages\PetsPage;

    $isRootView = $isRootView ?? false;
    $returnViewMode = $returnViewMode ?? 'blocks';
    $page = PetsPage::show($pet, $client, $isRootView, $returnViewMode);
    $breadcrumbs = $page['breadcrumbs'];
@endphp

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
        <a href="{{ route('clients.show', $client) }}" class="btn btn-outline-secondary">Ver cliente</a>
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-secondary">Editar cliente</a>
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
                        <div class="fw-semibold">{{ $booking->scheduled_at?->format('d/m/Y H:i') }}</div>
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
            <h3 class="h5">Nueva foto</h3>
            <form action="{{ route('clients.pets.photos.store', [$client, $pet]) }}" method="POST" enctype="multipart/form-data" class="row g-3 mt-1">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Archivo de foto</label>
                    <input type="file" name="photo" class="form-control" accept="image/*" required>
                    <div class="form-text">La imagen se optimiza a un maximo de 1600 px y se genera una miniatura compacta de 160 x 120 para catalogos y bloques de cliente.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <input type="text" name="photo_type" class="form-control" value="perfil" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de toma</label>
                    <input type="datetime-local" name="taken_at" class="form-control" data-photo-taken-at>
                    <div class="form-text" data-photo-exif-status>Si la dejas vacia, el sistema intentara leerla desde los metadatos EXIF de la foto.</div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="is_primary" value="0">
                        <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="photo-primary-create">
                        <label class="form-check-label" for="photo-primary-create">Foto principal</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripcion</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Crear foto</button>
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
                    <div class="border rounded p-3 mb-3 {{ $photo->is_primary ? 'border-primary' : '' }}">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Imagen actual</label>
                                <div>
                                    <img src="{{ $photo->photo_file_url }}" alt="Foto de {{ $pet->name }}" class="img-fluid rounded border mb-2 pet-photo-preview">
                                </div>
                                <a href="{{ $photo->photo_file_url }}" target="_blank" rel="noreferrer" class="btn btn-link px-0">Abrir foto</a>
                                <div class="mt-2">
                                    <label class="form-label">Reemplazar archivo</label>
                                    <input form="pet-photo-form-{{ $photo->id }}" type="file" name="photo" class="form-control" accept="image/*" data-photo-file-input>
                                    <div class="form-text">Si subes una nueva imagen, el sistema reemplaza la anterior, la redimensiona y regenera su miniatura.</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo</label>
                                <input form="pet-photo-form-{{ $photo->id }}" type="text" name="photo_type" value="{{ $photo->photo_type === 'profile' ? 'perfil' : $photo->photo_type }}" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fecha de toma</label>
                                <input form="pet-photo-form-{{ $photo->id }}" type="datetime-local" name="taken_at" value="{{ optional($photo->taken_at)->format('Y-m-d\TH:i') }}" class="form-control" data-photo-taken-at>
                                <div class="form-text" data-photo-exif-status>Si reemplazas la foto y dejas este campo vacio, se intentara usar la fecha EXIF.</div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input form="pet-photo-form-{{ $photo->id }}" type="hidden" name="is_primary" value="0">
                                    <input form="pet-photo-form-{{ $photo->id }}" class="form-check-input" type="checkbox" name="is_primary" value="1" id="photo-primary-{{ $photo->id }}" @checked($photo->is_primary)>
                                    <label class="form-check-label" for="photo-primary-{{ $photo->id }}">Principal</label>
                                </div>
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
@endsection