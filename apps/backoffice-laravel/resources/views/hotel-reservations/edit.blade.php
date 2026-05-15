@php
    $screenDebugId = 'AgHotEdi';
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
        <a href="{{ route('hotel-reservations.show', $hotelReservation) }}" class="btn btn-outline-secondary">Volver al detalle</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('hotel-reservations.update', $hotelReservation) }}" class="row g-3">
            @csrf
            @method('PUT')
            @include('hotel-reservations.partials.form')
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection