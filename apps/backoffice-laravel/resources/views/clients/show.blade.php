@php
    $screenDebugId = 'CliSho';

    $page = \App\Support\Pages\ClientsPage::show($client);
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
        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-secondary">Editar cliente</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">{{ $client->first_name }} {{ $client->last_name }}</h5>
        <p><strong>Email:</strong> {{ $client->email }}</p>
        <p><strong>Notas:</strong> {{ $client->notes ?: 'Ninguna' }}</p>

        <h6>Direcciones:</h6>
        @if($client->addresses->count() > 0)
            <ul>
                @foreach($client->addresses as $address)
                    <li>
                        <strong>{{ match($address->type) { 'home' => 'Casa', 'work' => 'Trabajo', default => ucfirst($address->type) } }}:</strong> {{ $address->formatted_address }}
                        @if($address->lat && $address->lng)
                            (Lat: {{ $address->lat }}, Lng: {{ $address->lng }})
                        @endif
                        @if($address->google_maps_url)
                            · <a href="{{ $address->google_maps_url }}" target="_blank" rel="noopener noreferrer">Maps</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p>No hay direcciones registradas.</p>
        @endif

        <h6>Teléfonos:</h6>
        @if($client->phones->count() > 0)
            <ul>
                @foreach($client->phones as $phone)
                    <li><strong>{{ match($phone->type) { 'mobile' => 'Móvil', 'home' => 'Casa', 'work' => 'Trabajo', 'other' => 'Otro', 'fixed' => 'Fijo', default => ucfirst($phone->type) } }}:</strong> {{ $phone->number }}@if($phone->extension) <span class="text-body-secondary">ext. {{ $phone->extension }}</span>@endif</li>
                @endforeach
            </ul>
        @else
            <p>No hay teléfonos adicionales.</p>
        @endif

        <h6>Mascotas vivas:</h6>
        @if($client->pets->count() > 0)
            @include('clients.partials.live-pets-grid', ['client' => $client, 'pets' => $client->pets])
        @else
            <p>No hay mascotas vivas registradas.</p>
        @endif
    </div>
</div>
@endsection