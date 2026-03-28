@extends('layouts.app')

@php
    use App\Support\Pages\ResourcesPage;

    $page = ResourcesPage::create();
    $breadcrumbs = $page['breadcrumbs'];
@endphp

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ old('return_to', $returnTo ?? route('resources.index')) }}" class="btn btn-outline-secondary">Regresar</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form action="{{ route('resources.store') }}" method="POST">
            @csrf
            @include('resources.partials.form', ['submitLabel' => 'Guardar recurso'])
        </form>
    </div>
</div>
@endsection