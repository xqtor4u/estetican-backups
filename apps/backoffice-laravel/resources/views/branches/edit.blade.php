@php
    $screenDebugId = 'BraEdi';

    $page = \App\Support\Pages\BranchesPage::edit($branch);
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
        <a href="{{ route('branches.show', $branch) }}" class="btn btn-outline-secondary">Ver detalle</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form action="{{ route('branches.update', $branch) }}" method="POST">
            @csrf
            @method('PUT')
            @include('branches.partials.form', ['submitLabel' => 'Actualizar sucursal'])
        </form>
    </div>
</div>
@endsection