@extends('layouts.app')

@section('content')
<h1>Detalles del Cliente</h1>
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
                        <strong>{{ ucfirst($address->type) }}:</strong> {{ $address->street }}, {{ $address->colonia }}, {{ $address->city }}, {{ $address->state }}, {{ $address->zip }}, {{ $address->country }}
                        @if($address->lat && $address->lng)
                            (Lat: {{ $address->lat }}, Lng: {{ $address->lng }})
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
                    <li><strong>{{ ucfirst($phone->type) }}:</strong> {{ $phone->number }}</li>
                @endforeach
            </ul>
        @else
            <p>No hay teléfonos adicionales.</p>
        @endif
    </div>
</div>
<a href="{{ route('clients.index') }}" class="btn btn-secondary mt-3">Volver</a>
@endsection