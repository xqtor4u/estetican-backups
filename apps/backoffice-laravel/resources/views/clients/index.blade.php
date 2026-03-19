@extends('layouts.app')

@section('content')
<h1>Clientes</h1>
<a href="{{ route('clients.create') }}" class="btn btn-primary mb-3">Crear Cliente</a>

@foreach($clients as $client)
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">{{ $client->first_name }} {{ $client->last_name }}</h5>
        <p class="card-text">Email: {{ $client->email }}</p>

        <h6>Direcciones:</h6>
        @if($client->addresses->count() > 0)
            <ul>
                @foreach($client->addresses as $address)
                    <li>{{ $address->type }}: {{ $address->street }}, {{ $address->colonia }}, {{ $address->city }}, {{ $address->state }}, {{ $address->country }}</li>
                @endforeach
            </ul>
        @else
            <p>No hay direcciones.</p>
        @endif

        <h6>Teléfonos:</h6>
        @if($client->phones->count() > 0)
            <ul>
                @foreach($client->phones as $phone)
                    <li>{{ $phone->type }}: {{ $phone->number }}</li>
                @endforeach
            </ul>
        @else
            <p>No hay teléfonos.</p>
        @endif

        <a href="{{ route('clients.show', $client) }}" class="btn btn-info">Ver</a>
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-warning">Editar</a>
        <form action="{{ route('clients.destroy', $client) }}" method="POST" style="display:inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Eliminar?')">Eliminar</button>
        </form>
    </div>
</div>
@endforeach

{{ $clients->links() }}
@endsection
