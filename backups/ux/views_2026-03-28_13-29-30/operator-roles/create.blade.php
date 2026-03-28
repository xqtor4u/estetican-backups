@extends('layouts.app')

@php
    use App\Support\Pages\OperatorRolesPage;

    $page = OperatorRolesPage::create();
    $breadcrumbs = $page['breadcrumbs'];
@endphp

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ old('return_to', $returnTo ?? url()->previous()) }}" class="btn btn-outline-secondary">Regresar</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form action="{{ route('operator-roles.store') }}" method="POST">
            @csrf
            <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo ?? url()->previous()) }}">
            @include('operator-roles.partials.form', ['submitLabel' => 'Guardar tipo'])
        </form>
    </div>
</div>
@endsection