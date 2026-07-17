@php
    $screenDebugId = 'AgNew';
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')
@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
/>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-lg-5">
            <div id="bookingWizard" x-data="bookingWizard()">
                <!-- Step 1: Select Pet -->
                <div x-show="step === 1" x-transition>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Buscar Mascota</label>
                        <input 
                            type="text" 
                            class="form-control form-control-lg rounded-pill" 
                            placeholder="Nombre de la mascota o el cliente..."
                            x-model="search"
                        >
                    </div>

                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2 mb-2" style="max-height: 500px; overflow-y: auto; padding: 0.25rem;">
                        <template x-for="pet in filteredPets" :key="pet.id">
                            <div class="col">
                                <button 
                                    type="button" 
                                    class="card w-100 border-0 shadow-sm text-start hover-elevate transition-all"
                                    @click="selectPet(pet)"
                                >
                                    <div class="card-body px-3 py-2 w-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="text-truncate pe-2">
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <span class="fw-bold text-dark" x-text="pet.name"></span>
                                                    <span class="badge bg-light text-dark border fw-normal py-1" style="font-size: 0.65rem;" x-text="pet.species || 'S/E'"></span>
                                                </div>
                                                <div class="text-muted text-truncate" style="font-size: 0.8rem;" title="Cliente">
                                                    <i class="bi bi-person me-1"></i><span x-text="pet.client.first_name + ' ' + (pet.client.last_name || '')"></span>
                                                </div>
                                            </div>
                                            <i class="bi bi-chevron-right text-primary opacity-50 flex-shrink-0"></i>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </template>
                        <div x-show="filteredPets.length === 0" class="col-12 p-4 text-center text-body-secondary">
                            No se encontraron mascotas que coincidan con tu búsqueda.
                        </div>
                    </div>
                </div>

                <!-- Step 2: Select Type -->
                <div x-show="step === 2" x-transition>
                    <div class="text-center mb-4">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none mb-3" @click="step = 1">
                            <i class="bi bi-arrow-left"></i> Cambiar mascota
                        </button>
                        <h3 class="h4">¿Qué tipo de servicio requiere <span class="text-primary" x-text="selectedPet?.name"></span>?</h3>
                        <p class="text-body-secondary">Elige la línea de negocio para continuar.</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <button 
                                type="button" 
                                class="card h-100 border-0 shadow-sm text-center p-4 w-100 hover-elevate transition-all"
                                @click="goToCreate('spa')"
                            >
                                <div class="card-body">
                                    <div class="display-5 text-primary mb-3"><i class="bi bi-scissors"></i></div>
                                    <h5 class="card-title fw-bold">Servicio de SPA</h5>
                                    <p class="card-text text-body-secondary small">Baño, corte, cepillado y servicios estéticos puntuales.</p>
                                </div>
                            </button>
                        </div>
                        @if($hotelModuleEnabled)
                            <div class="col-md-6">
                                <button
                                    type="button"
                                    class="card h-100 border-0 shadow-sm text-center p-4 w-100 hover-elevate transition-all"
                                    @click="goToCreate('hotel')"
                                >
                                    <div class="card-body">
                                        <div class="display-5 text-info mb-3"><i class="bi bi-house-heart"></i></div>
                                        <h5 class="card-title fw-bold">Hospedaje (Hotel)</h5>
                                        <p class="card-text text-body-secondary small">Estancias nocturnas, guardería y cuidados prolongados.</p>
                                    </div>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script nonce="{{ csp_nonce() }}">
    function bookingWizard() {
        return {
            step: 1,
            search: '',
            selectedPet: null,
            pets: @json($pets),
            get filteredPets() {
                if (!this.search) return this.pets.slice(0, 50);
                const s = this.search.toLowerCase();
                return this.pets.filter(p => 
                    p.name.toLowerCase().includes(s) || 
                    (p.client.first_name + ' ' + (p.client.last_name || '')).toLowerCase().includes(s)
                );
            },
            selectPet(pet) {
                this.selectedPet = pet;
                this.step = 2;
            },
            goToCreate(type) {
                if (!this.selectedPet) return;
                
                let url = '';
                if (type === 'spa') {
                    url = "{{ route('pets.bookings.create', ':pet') }}".replace(':pet', this.selectedPet.id);
                } else {
                    // For Hotel, we use the standard hotel-reservations.create and set pet_id as query param
                    url = "{{ route('hotel-reservations.create') }}?pet_id=" + this.selectedPet.id;
                }
                
                window.location.href = url;
            }
        }
    }
</script>
<style>
    .hover-elevate:hover {
        transform: translateY(-5px);
        box-shadow: 0 1rem 3rem rgba(0,0,0,.1) !important;
        background-color: var(--bs-light);
    }
    .transition-all {
        transition: all 0.3s ease;
    }
</style>
@endpush
@endsection
