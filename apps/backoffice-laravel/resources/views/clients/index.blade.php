@php
    $screenDebugId = 'CliInd';

    $page = \App\Support\Pages\ClientsPage::index();
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
            <x-list-view-toggle route="clients.index" :view-mode="$viewMode" aria-label="Cambiar presentación de clientes" />
            <a href="{{ route('clients.create') }}" class="btn btn-primary">Crear cliente</a>
        </div>
    </x-slot:actions>
</x-page-header>

@if($petCreationMode)
    <div class="alert alert-info">Busca al cliente dueño de la mascota nueva y presiona "Agregar mascota aquí" en su tarjeta.</div>
@endif

<div class="catalog-content-wide">
<x-list-filters :action="route('clients.index')" :view-mode="$viewMode" :reset-url="route('clients.index', ['view' => $viewMode])">
    @if($petCreationMode)
        <input type="hidden" name="mode" value="pet_creation">
    @endif
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Buscar</label>
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Cliente, email, telefono o mascota">
    </div>
    <div class="col-lg-2 col-md-6">
        <label class="form-label">Mascotas vivas</label>
        <select name="pets_filter" class="form-select">
            <option value="all" @selected($petsFilter === 'all')>Todos</option>
            <option value="with_pets" @selected($petsFilter === 'with_pets')>Con mascotas</option>
            <option value="without_pets" @selected($petsFilter === 'without_pets')>Sin mascotas</option>
        </select>
    </div>
    <div class="col-lg-2 col-md-6">
        <label class="form-label">Ordenar por</label>
        <select name="sort" class="form-select">
            <option value="" @selected(!$sort)>Orden base</option>
            <option value="first_name" @selected($sort === 'name' || $sort === 'first_name')>Nombre</option>
            <option value="last_name" @selected($sort === 'last_name')>Apellido</option>
            <option value="email" @selected($sort === 'email')>Email</option>
            <option value="pets" @selected($sort === 'pets')>Mascotas vivas</option>
        </select>
    </div>
    <div class="col-lg-2 col-md-6">
        <label class="form-label">Sentido</label>
        <select name="direction" class="form-select">
            <option value="asc" @selected($direction === 'asc')>Asc</option>
            <option value="desc" @selected($direction === 'desc')>Desc</option>
        </select>
    </div>
</x-list-filters>

@if($viewMode === 'table')
    @include('clients.partials.index-table', ['clients' => $clients, 'sort' => $sort, 'direction' => $direction, 'viewMode' => $viewMode, 'petCreationMode' => $petCreationMode])
@else
    @include('clients.partials.index-blocks', ['clients' => $clients, 'petCreationMode' => $petCreationMode])

    {{ $clients->links() }}
@endif
</div>
@endsection
