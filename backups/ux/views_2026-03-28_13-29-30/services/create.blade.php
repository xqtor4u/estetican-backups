@extends('layouts.app')

@php
    use App\Support\Pages\ServicesPage;

    $page = ServicesPage::create();
    $breadcrumbs = $page['breadcrumbs'];
@endphp

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
/>

<div class="card">
    <div class="card-body">
        <form action="{{ route('services.store') }}" method="POST">
            @csrf
            @include('services.partials.form', ['submitLabel' => 'Guardar servicio'])
        </form>
    </div>
</div>
@endsection