@php
    $screenDebugId = 'CliEdi';

    $page = \App\Support\Pages\ClientsPage::edit($client);
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
        <a href="{{ route('clients.show', $client) }}" class="btn btn-outline-secondary">Ver cliente</a>
        <a href="{{ route('clients.index') }}" class="btn btn-secondary">Regresar</a>
    </x-slot:actions>
</x-page-header>

<form id="client-edit-form" action="{{ route('clients.update', $client) }}" method="POST" data-pet-default-species="{{ $defaultSpecies ?? '' }}" novalidate>
    @csrf
    @method('PUT')
    <div class="mb-2">
        <label class="form-label small mb-1">Nombre</label>
        <input type="text" name="first_name" value="{{ old('first_name', $client->first_name) }}" class="form-control form-control-sm" required>
    </div>
    <div class="mb-2">
        <label class="form-label small mb-1">Apellido paterno</label>
        <input type="text" name="apellido_paterno" value="{{ old('apellido_paterno', $client->apellido_paterno) }}" class="form-control form-control-sm">
    </div>
    <div class="mb-2">
        <label class="form-label small mb-1">Apellido materno</label>
        <input type="text" name="apellido_materno" value="{{ old('apellido_materno', $client->apellido_materno) }}" class="form-control form-control-sm">
    </div>
    <div class="mb-3">
        <label class="form-label small mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email', $client->email) }}" class="form-control form-control-sm">
        <div class="form-text">Opcional. Debe existir al menos un telefono activo para conservar la captura.</div>
    </div>

    <h4>Preferencias de comunicación</h4>
    <div class="mb-3 d-grid gap-2">
        @php
            $commPrefs = [
                'receives_offers' => 'Ofertas y promociones',
                'receives_service_reminders' => 'Recordatorios de servicio (citas, recurrencias)',
                'receives_job_updates' => 'Estado de trabajo y resúmenes de atención',
                'receives_account_statements' => 'Estado de cuenta',
                'receives_other_notifications' => 'Otras notificaciones',
            ];
        @endphp
        @foreach($commPrefs as $field => $label)
            <div class="form-check form-switch">
                <input type="hidden" name="{{ $field }}" value="0">
                <input type="checkbox" name="{{ $field }}" value="1" class="form-check-input" id="{{ $field }}"
                    {{ old($field, $client->$field) ? 'checked' : '' }}>
                <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
            </div>
        @endforeach
        <div class="form-text">El cliente también puede administrar esto sin ayuda desde el enlace "Gestionar mis preferencias" que llevan los correos que recibe.</div>
    </div>


    <h4>Direcciones</h4>
    <div id="addresses" class="d-grid gap-2">
        @foreach($client->addresses as $i => $address)
            <input type="hidden" name="addresses[{{ $i }}][id]" value="{{ $address->id }}">
            @include('shared.address-editor', [
                'address' => $address,
                'namePrefix' => 'addresses['.$i.']',
                'oldPrefix' => 'addresses.'.$i,
                'showType' => true,
                'allowDelete' => true,
                'deleteName' => 'addresses['.$i.'][delete]',
                'deleteId' => 'address-delete-'.$i,
                'useCard' => true,
                'title' => 'Dirección '.($i + 1),
                'subtitle' => 'Editar la dirección completa y sus coordenadas desde un bloque compacto.',
                'wrapperClass' => 'address-item shadow-sm',
                'wrapperAttributes' => ['data-address-index' => $i],
            ])
        @endforeach
    </div>
    <template id="client-address-template">
        @include('shared.address-editor', [
            'namePrefix' => 'addresses[__INDEX__]',
            'showType' => true,
            'allowDelete' => true,
            'deleteName' => 'addresses[__INDEX__][delete]',
            'deleteId' => 'address-delete-__INDEX__',
            'useCard' => true,
            'title' => 'Dirección __TITLE_INDEX__',
            'subtitle' => 'Editar la dirección completa y sus coordenadas desde un bloque compacto.',
            'wrapperClass' => 'address-item shadow-sm',
            'wrapperAttributes' => ['data-address-index' => '__INDEX__'],
        ])
    </template>
    <button type="button" class="btn btn-sm btn-secondary" data-client-edit-action="show-address-modal">Agregar Dirección</button>

    <h4>Teléfonos</h4>
    <table class="table table-bordered" id="phones">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Número</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
        @foreach($client->phones as $j => $phone)
            <tr class="phone-item">
                <input type="hidden" name="phones[{{ $j }}][id]" value="{{ $phone->id }}">
                <td>
                    <select name="phones[{{ $j }}][type]" class="form-control" required>
                        <option value="mobile" @if($phone->type=='mobile') selected @endif>Móvil</option>
                        <option value="fixed" @if($phone->type=='fixed') selected @endif>Fijo</option>
                    </select>
                </td>
                <td><input type="text" name="phones[{{ $j }}][number]" value="{{ $phone->number }}" class="form-control" required></td>
                <td class="text-center align-middle">
                    <input type="checkbox" name="phones[{{ $j }}][delete]" value="1">
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
        <button type="button" class="btn btn-secondary" data-client-edit-action="show-phone-modal">Agregar Teléfono</button>

    <h4 class="mt-4">Mascotas</h4>
    <table class="table table-bordered" id="pets">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Especie</th>
                <th>Raza</th>
                <th>Fecha nac.</th>
                <th>Fecha deceso</th>
                <th>Chip</th>
                <th>Tatuaje</th>
                <th>Sexo</th>
                <th>Color</th>
                <th>Tamano</th>
                <th>Esterilizado</th>
                <th>Notas</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
        @foreach($client->pets as $k => $pet)
            <tr class="pet-item {{ $pet->death_date ? 'table-secondary' : '' }}">
                <input type="hidden" name="pets[{{ $k }}][id]" value="{{ $pet->id }}">
                <td>
                    <input type="text" name="pets[{{ $k }}][name]" value="{{ $pet->name }}" class="form-control" required>
                    @if($pet->death_date)
                        <small class="text-muted d-block mt-1">Fallecido</small>
                    @endif
                </td>
                <td><input type="text" name="pets[{{ $k }}][species]" value="{{ $pet->species }}" class="form-control"></td>
                <td><input type="text" name="pets[{{ $k }}][breed]" value="{{ $pet->breed }}" class="form-control"></td>
                <td>
                    <input type="date" name="pets[{{ $k }}][birth_date]" value="{{ optional($pet->birth_date)->format('Y-m-d') }}" class="form-control">
                    @if($pet->birth_date && !$pet->death_date && $pet->age_description)
                        <small class="text-muted d-block mt-1">{{ $pet->age_description }}</small>
                    @endif
                </td>
                <td>
                    <input type="date" name="pets[{{ $k }}][death_date]" value="{{ optional($pet->death_date)->format('Y-m-d') }}" class="form-control">
                    @if($pet->death_date && $pet->age_description)
                        <small class="text-muted d-block mt-1">{{ $pet->age_description }}</small>
                    @endif
                </td>
                <td><input type="text" name="pets[{{ $k }}][microchip_code]" value="{{ $pet->microchip_code }}" class="form-control"></td>
                <td><input type="text" name="pets[{{ $k }}][tattoo_code]" value="{{ $pet->tattoo_code }}" class="form-control"></td>
                <td>
                    <select name="pets[{{ $k }}][sex]" class="form-control">
                        <option value="" @selected(!$pet->sex)>Seleccionar</option>
                        <option value="male" @selected($pet->sex === 'male')>Macho</option>
                        <option value="female" @selected($pet->sex === 'female')>Hembra</option>
                        <option value="unknown" @selected($pet->sex === 'unknown')>No definido</option>
                    </select>
                </td>
                <td><input type="text" name="pets[{{ $k }}][coat_color]" value="{{ $pet->coat_color }}" class="form-control"></td>
                <td>
                    <select name="pets[{{ $k }}][size]" class="form-control">
                        <option value="" @selected(!$pet->size)>Seleccionar</option>
                        <option value="mini" @selected($pet->size === 'mini')>Mini</option>
                        <option value="small" @selected($pet->size === 'small')>Pequeno</option>
                        <option value="medium" @selected($pet->size === 'medium')>Mediano</option>
                        <option value="large" @selected($pet->size === 'large')>Grande</option>
                        <option value="giant" @selected($pet->size === 'giant')>Gigante</option>
                    </select>
                </td>
                <td class="text-center align-middle">
                    <input type="hidden" name="pets[{{ $k }}][is_sterilized]" value="0">
                    <input type="checkbox" name="pets[{{ $k }}][is_sterilized]" value="1" @checked($pet->is_sterilized)>
                </td>
                <td><textarea name="pets[{{ $k }}][notes]" class="form-control" rows="2">{{ $pet->notes }}</textarea></td>
                <td class="text-center align-middle">
                    <input type="checkbox" name="pets[{{ $k }}][delete]" value="1">
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <button type="button" class="btn btn-secondary" data-client-edit-action="show-pet-modal">Agregar Mascota</button>

    @if($client->pets->isNotEmpty())
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Seleccionar mascota para gestionar tablas dependientes</h5>
                <p class="text-muted">Usa esta vista cuando necesites trabajar directamente con alertas medicas y fotos de una mascota.</p>
                <div class="row g-3">
                    @foreach($client->pets as $pet)
                        <div class="col-12 col-xl-6">
                            <a href="{{ route('clients.pets.show', [$client, $pet]) }}" class="card h-100 text-decoration-none {{ $pet->death_date ? 'opacity-75 border-secondary' : '' }}">
                                <div class="card-body d-flex gap-3 align-items-start">
                                    <div class="bg-light border rounded overflow-hidden flex-shrink-0 app-media-thumb">
                                        @if($pet->catalog_thumbnail_url)
                                            <img src="{{ $pet->catalog_thumbnail_url }}" alt="Foto de {{ $pet->name }}" width="160" height="120" class="w-100 h-100 app-media-cover">
                                        @else
                                            <div class="d-flex align-items-center justify-content-center text-muted small w-100 h-100">Sin foto</div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $pet->name }}</div>
                                        <div class="small text-muted">
                                            @if($pet->species_label)
                                                {{ $pet->species_label }}
                                            @endif
                                            @if($pet->species_label && $pet->breed)
                                                ·
                                            @endif
                                            @if($pet->breed)
                                                {{ $pet->breed }}
                                            @endif
                                        </div>
                                        @if($pet->death_date)
                                            <div class="small text-secondary mt-2">Fallecida</div>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <p class="text-muted mt-3 mb-0">Guarda al menos una mascota para habilitar el CRUD de sus tablas dependientes.</p>
    @endif

<!-- Modal Dirección -->
<div class="modal fade" id="addressModal" tabindex="-1" aria-labelledby="addressModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addressModalLabel">Agregar Dirección</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label>Tipo:</label>
                    <select id="modalAddressType" class="form-control">
                        <option value="home">Casa</option>
                        <option value="work">Trabajo</option>
                    </select>
                </div>
                <div class="mb-2"><label>Calle:</label><input type="text" id="modalAddressStreet" class="form-control"></div>
                <div class="mb-2"><label>Número exterior:</label><input type="text" id="modalAddressExteriorNumber" class="form-control"></div>
                <div class="mb-2"><label>Interior:</label><input type="text" id="modalAddressInteriorNumber" class="form-control"></div>
                <div class="mb-2"><label>Colonia:</label><input type="text" id="modalAddressColonia" class="form-control"></div>
                <div class="mb-2"><label>Ciudad:</label><input type="text" id="modalAddressCity" class="form-control"></div>
                <div class="mb-2"><label>Estado:</label><input type="text" id="modalAddressState" class="form-control"></div>
                <div class="mb-2"><label>Código postal:</label><input type="text" id="modalAddressZip" class="form-control"></div>
                <div class="mb-2"><label>País:</label><input type="text" id="modalAddressCountry" class="form-control" value="México"></div>
                <div class="mb-2"><label>Latitud:</label><input type="number" id="modalAddressLat" class="form-control" step="0.00000001" min="-90" max="90"></div>
                <div class="mb-2"><label>Longitud:</label><input type="number" id="modalAddressLng" class="form-control" step="0.00000001" min="-180" max="180"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" data-client-edit-action="confirm-address-modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Teléfono -->
<div class="modal fade" id="phoneModal" tabindex="-1" aria-labelledby="phoneModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="phoneModalLabel">Agregar Teléfono</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label>Tipo:</label>
                    <select id="modalPhoneType" class="form-control">
                        <option value="mobile">Móvil</option>
                        <option value="fixed">Fijo</option>
                    </select>
                </div>
                <div class="mb-2"><label>Número:</label><input type="text" id="modalPhoneNumber" class="form-control"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" data-client-edit-action="confirm-phone-modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Mascota -->
<div class="modal fade" id="petModal" tabindex="-1" aria-labelledby="petModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="petModalLabel">Agregar Mascota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><label>Nombre:</label><input type="text" id="modalPetName" class="form-control"></div>
                <div class="mb-2"><label>Especie:</label><input type="text" id="modalPetSpecies" class="form-control" placeholder="Perro, Gato, Pajaro..."></div>
                <div class="mb-2"><label>Raza:</label><input type="text" id="modalPetBreed" class="form-control"></div>
                <div class="mb-2"><label>Fecha de nacimiento:</label><input type="date" id="modalPetBirthDate" class="form-control"></div>
                <div class="mb-2"><label>Fecha de deceso:</label><input type="date" id="modalPetDeathDate" class="form-control"></div>
                <div class="mb-2"><label>Numero de chip:</label><input type="text" id="modalPetMicrochip" class="form-control"></div>
                <div class="mb-2"><label>Numero de tatuaje:</label><input type="text" id="modalPetTattoo" class="form-control"></div>
                <div class="mb-2">
                    <label>Sexo:</label>
                    <select id="modalPetSex" class="form-control">
                        <option value="">Seleccionar</option>
                        <option value="male">Macho</option>
                        <option value="female">Hembra</option>
                        <option value="unknown">No definido</option>
                    </select>
                </div>
                <div class="mb-2"><label>Color:</label><input type="text" id="modalPetCoatColor" class="form-control"></div>
                <div class="mb-2">
                    <label>Tamano:</label>
                    <select id="modalPetSize" class="form-control">
                        <option value="">Seleccionar</option>
                        <option value="mini">Mini</option>
                        <option value="small">Pequeno</option>
                        <option value="medium">Mediano</option>
                        <option value="large">Grande</option>
                        <option value="giant">Gigante</option>
                    </select>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="modalPetSterilized">
                    <label class="form-check-label" for="modalPetSterilized">Esterilizado</label>
                </div>
                <div class="mb-2"><label>Notas:</label><textarea id="modalPetNotes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" data-client-edit-action="confirm-pet-modal">OK</button>
            </div>
        </div>
    </div>
</div>

    <br><br>
    <button type="submit" class="btn btn-success">Actualizar</button>
    <a href="{{ route('clients.index') }}" class="btn btn-secondary">Cancelar</a>
</form>

@endsection