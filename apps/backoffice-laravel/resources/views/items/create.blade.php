@php
    $screenDebugId = 'ArtCre';

    $page = \App\Support\Pages\ItemsPage::create();
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form action="{{ route('items.store') }}" method="POST">
                    @csrf
                    @include('items.partials.form', ['submitLabel' => 'Guardar artículo'])
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
