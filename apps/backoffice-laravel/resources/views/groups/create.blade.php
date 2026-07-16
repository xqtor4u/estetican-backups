@php
    $screenDebugId = 'GrpCre';

    $page = \App\Support\Pages\GroupsPage::create();
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form action="{{ route('groups.store') }}" method="POST">
                    @csrf
                    @include('groups.partials.form', ['submitLabel' => 'Guardar grupo'])
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
