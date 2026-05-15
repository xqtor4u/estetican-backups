@php
    $screenDebugId = 'AgHotNew';
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
        <a href="{{ route('hotel-reservations.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('hotel-reservations.store') }}" class="row g-3">
            @csrf
            @php($hotelReservation = null)
            @php($assignedResourceId = null)
            @php($hotelReservation = (object)['pet_id' => $selectedPetId ?? null, 'start_at' => null, 'end_at' => null])
            @include('hotel-reservations.partials.form')
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Crear reserva</button>
            </div>
        </form>
    </div>
</div>
@endsection