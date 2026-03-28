@extends('layouts.app')

@php
    use App\Support\Pages\OperatorsPage;

    $page = OperatorsPage::create();
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
        <form action="{{ route('operators.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('operators.partials.form', ['submitLabel' => 'Guardar operador'])
        </form>
    </div>
</div>
@endsection