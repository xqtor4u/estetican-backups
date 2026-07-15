@php
    $screenDebugId = 'ArtEdi';

    $page = \App\Support\Pages\ItemsPage::edit($item);
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
/>

<div class="card">
    <div class="card-body">
        <form action="{{ route('items.update', $item) }}" method="POST">
            @csrf
            @method('PUT')
            @include('items.partials.form', ['submitLabel' => 'Actualizar artículo'])
        </form>
    </div>
</div>
@endsection
