@php
    $screenDebugId = 'ResEdi';

    $page = \App\Support\Pages\ResourcesPage::edit($resource);
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
        <a href="{{ route('resources.show', $resource) }}" class="btn btn-outline-secondary">Ver detalle</a>
        <form action="{{ route('resources.duplicate', $resource) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Duplicar recurso</button>
        </form>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form action="{{ route('resources.update', $resource) }}" method="POST">
            @csrf
            @method('PUT')
            @include('resources.partials.form', ['submitLabel' => 'Actualizar recurso'])
        </form>
    </div>
</div>
@endsection