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
    @forelse($clients as $client)
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">{{ $client->first_name }} {{ $client->last_name }}</h5>
                <p class="card-text">Email: {{ $client->email }}</p>

                <h6>Direcciones:</h6>
                @if($client->addresses->count() > 0)
                    <ul>
                        @foreach($client->addresses as $address)
                            <li>{{ match($address->type) { 'home' => 'Casa', 'work' => 'Trabajo', default => ucfirst($address->type) } }}: {{ $address->formatted_address }}</li>
                        @endforeach
                    </ul>
                @else
                    <p>No hay direcciones.</p>
                @endif

                <h6>Teléfonos:</h6>
                @if($client->phones->count() > 0)
                    <ul>
                        @foreach($client->phones as $phone)
                            <li>{{ match($phone->type) { 'mobile' => 'Móvil', 'fixed' => 'Fijo', default => ucfirst($phone->type) } }}: {{ $phone->number }}</li>
                        @endforeach
                    </ul>
                @else
                    <p>No hay teléfonos.</p>
                @endif

                <h6>Mascotas vivas:</h6>
                @if($client->pets->count() > 0)
                    @include('clients.partials.live-pets-grid', ['client' => $client, 'pets' => $client->pets])
                @else
                    <p>No hay mascotas vivas.</p>
                @endif

                @if($petCreationMode)
                    <a href="{{ route('clients.edit', ['client' => $client, 'open_pet_modal' => 1]) }}" class="btn btn-primary">Agregar mascota aquí</a>
                @else
                    <a href="{{ route('clients.show', $client) }}" class="btn btn-info">Ver</a>
                    <a href="{{ route('clients.edit', $client) }}" class="btn btn-warning">Editar</a>
                    <form action="{{ route('clients.destroy', $client) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" data-confirm="¿Eliminar?">Eliminar</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center py-4 text-body-secondary">No hay clientes que coincidan con los filtros actuales.</div>
        </div>
    @endforelse

    {{ $clients->links() }}
@endif
</div>
@endsection
