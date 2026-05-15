@php
    $screenDebugId = 'OprRolEdi';
    use App\Support\Pages\OperatorRolesPage;

    $page = OperatorRolesPage::edit($operatorRole);
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
        <a href="{{ route('operator-roles.show', $operatorRole) }}" class="btn btn-outline-secondary">Ver detalle</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form action="{{ route('operator-roles.update', $operatorRole) }}" method="POST">
            @csrf
            @method('PUT')
            @include('operator-roles.partials.form', ['submitLabel' => 'Actualizar tipo'])
        </form>
    </div>
</div>
@endsection