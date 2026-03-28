@extends('layouts.app')

@section('content')
<x-page-header eyebrow="Agenda Hotel" title="Nueva reserva hotel" subtitle="Reserva planeada con jaula bloqueada por rango de tiempo.">
    <x-slot:actions>
        <a href="{{ route('hotel-reservations.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('hotel-reservations.store') }}" class="row g-3">
            @csrf
            @php($hotelReservation = null)
            @php($assignedResourceId = null)
            @include('hotel-reservations.partials.form')
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Crear reserva</button>
            </div>
        </form>
    </div>
</div>
@endsection