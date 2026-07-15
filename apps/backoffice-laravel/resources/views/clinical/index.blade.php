@php
    $screenDebugId = $page['screen_id'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
/>

<x-list-filters :action="route('clinical.index')" :reset-url="route('clinical.index')">
    <div class="col-lg-6 col-md-8">
        <label class="form-label">Buscar mascota o dueño</label>
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Nombre de la mascota o del dueño">
    </div>
</x-list-filters>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Mascota</th>
                    <th>Dueño</th>
                    <th class="text-end">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pets as $pet)
                    <tr>
                        <td class="fw-semibold">{{ $pet->name }}</td>
                        <td>{{ $pet->client?->full_name }}</td>
                        <td class="text-end">
                            <a href="{{ route('clinical.pets.show', $pet) }}" class="btn btn-sm btn-outline-primary">Ver expediente</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-body-secondary py-4">No se encontraron mascotas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $pets->links() }}
</div>
@endsection
