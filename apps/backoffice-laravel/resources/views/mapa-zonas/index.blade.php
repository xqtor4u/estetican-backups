@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
/>

<div
    class="catalog-content-wide"
    x-data="mapaZonas({
        csrfToken: '{{ csrf_token() }}',
        updatePetUrlTemplate: '{{ route('mapa-zonas.pets.ubicacion', ['pet' => '__PET_ID__']) }}',
        storeVehicleUrl: '{{ route('mapa-zonas.vehiculos.store') }}',
        branches: {{ \Illuminate\Support\Js::from($branches) }},
        clients: {{ \Illuminate\Support\Js::from($clients) }},
        pets: {{ \Illuminate\Support\Js::from($pets) }},
        vehicles: {{ \Illuminate\Support\Js::from($vehicles) }},
        unlocatedPets: {{ \Illuminate\Support\Js::from($unlocatedPets) }},
    })"
>
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body d-flex flex-wrap gap-3 small">
            <label class="d-flex align-items-center gap-1 mb-0">
                <input type="checkbox" class="form-check-input" x-model="showBranches">
                <span>Sucursales</span>
            </label>
            <label class="d-flex align-items-center gap-1 mb-0">
                <input type="checkbox" class="form-check-input" x-model="showClients">
                <span>Clientes</span>
            </label>
            <label class="d-flex align-items-center gap-1 mb-0">
                <input type="checkbox" class="form-check-input" x-model="showPets">
                <span>Mascotas</span>
            </label>
            <label class="d-flex align-items-center gap-1 mb-0">
                <input type="checkbox" class="form-check-input" x-model="showVehicles">
                <span>Vehículos</span>
            </label>
            <span class="text-muted ms-auto">Clic en el mapa para ubicar una mascota o agregar un vehículo.</span>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div x-ref="mapContainer" style="height: 70vh; border-radius: 0.5rem;"></div>
        </div>
    </div>

    <div class="modal fade" id="modalMapaZonasPlacement" x-ref="placementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Agregar punto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-danger small mb-3" x-show="placementError" x-text="placementError"></div>

                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="placementMode" id="placementModePet" value="pet" x-model="placementMode">
                        <label class="btn btn-outline-primary" for="placementModePet">Ubicar mascota</label>

                        <input type="radio" class="btn-check" name="placementMode" id="placementModeVehicle" value="vehicle" x-model="placementMode">
                        <label class="btn btn-outline-primary" for="placementModeVehicle">Nuevo vehículo</label>
                    </div>

                    <template x-if="placementMode === 'pet'">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mascota sin ubicación</label>
                            <select class="form-select" x-model="selectedPetId">
                                <option value="">Selecciona una mascota…</option>
                                <template x-for="p in unlocatedPets" :key="p.id">
                                    <option :value="p.id" x-text="p.label"></option>
                                </template>
                            </select>
                            <div class="form-text" x-show="unlocatedPets.length === 0">Todas las mascotas ya tienen una ubicación.</div>
                        </div>
                    </template>

                    <template x-if="placementMode === 'vehicle'">
                        <div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nombre del vehículo</label>
                                <input type="text" class="form-control" x-model="newVehicleName">
                            </div>
                            <div class="mb-1">
                                <label class="form-label fw-semibold">Notas</label>
                                <textarea class="form-control" rows="2" x-model="newVehicleNotes"></textarea>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" @click="submitPlacement()" :disabled="placing">
                        <span x-show="!placing">Guardar</span>
                        <span x-show="placing">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
