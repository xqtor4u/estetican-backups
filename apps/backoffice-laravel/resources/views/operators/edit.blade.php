@php
    $screenDebugId = 'OpeEdi';

    $page = \App\Support\Pages\OperatorsPage::edit($operator);
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
        <a href="{{ route('operators.show', $operator) }}" class="btn btn-outline-secondary">Ver detalle</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form action="{{ route('operators.update', $operator) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('operators.partials.form', ['submitLabel' => 'Actualizar operador'])
        </form>
    </div>
</div>
@endsection