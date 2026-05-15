@php
    $screenDebugId = 'PetInd';
    use App\Support\Pages\PetsPage;

    $page = PetsPage::index();
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
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <x-list-view-toggle route="pets.index" :view-mode="$viewMode" aria-label="Cambiar presentación de mascotas" />
            <a href="{{ route('agenda.index') }}" class="btn btn-outline-dark">Abrir agenda</a>
            <a href="{{ route('clients.index', ['mode' => 'pet_creation']) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Nueva mascota
            </a>
        </div>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
<x-list-filters :action="route('pets.index')" :view-mode="$viewMode" :reset-url="route('pets.index', ['view' => $viewMode])">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Mascota, cliente, email o raza">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Especie</label>
                <select name="species" class="form-select">
                    <option value="">Todas</option>
                    @foreach($speciesOptions as $speciesOption)
                        <option value="{{ $speciesOption }}" @selected($species === $speciesOption)>{{ ucfirst($speciesOption) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Estado</label>
                <select name="status" class="form-select">
                    <option value="all" @selected($status === 'all')>Todas</option>
                    <option value="active" @selected($status === 'active')>Activas</option>
                    <option value="deceased" @selected($status === 'deceased')>Fallecidas</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Ordenar por</label>
                <select name="sort" class="form-select">
                    <option value="" @selected(!$sort)>Orden base</option>
                    <option value="name" @selected($sort === 'name')>Nombre</option>
                    <option value="client" @selected($sort === 'client')>Cliente</option>
                    <option value="species" @selected($sort === 'species')>Especie</option>
                    <option value="status" @selected($sort === 'status')>Estado</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-6">
                <label class="form-label">Sentido</label>
                <select name="direction" class="form-select">
                    <option value="asc" @selected($direction === 'asc')>Asc</option>
                    <option value="desc" @selected($direction === 'desc')>Desc</option>
                </select>
            </div>
</x-list-filters>

@if($viewMode === 'table')
    @include('pets.partials.index-table', ['pets' => $pets, 'viewMode' => $viewMode, 'sort' => $sort, 'direction' => $direction])
@else
    @include('pets.partials.index-blocks', ['pets' => $pets, 'viewMode' => $viewMode])
    <div class="mt-3">
        {{ $pets->links() }}
    </div>
@endif
</div>
@endsection

@push('scripts')
<script>
    function confirmPetDelete(button) {
        const form = button.closest('form');
        window.premiumConfirm('¿Estás seguro de que deseas eliminar esta mascota? Se aplicará un borrado lógico (Soft Delete) y la información se conservará para fines de marketing histórico.', () => {
            form.submit();
        });
    }
</script>
@endpush