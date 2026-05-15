@php
    $screenDebugId = 'BraNew';
    use App\Support\Pages\BranchesPage;

    $page = BranchesPage::create();
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
        <a href="{{ old('return_to', $returnTo ?? url()->previous()) }}" class="btn btn-outline-secondary">Regresar</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form action="{{ route('branches.store') }}" method="POST">
            @csrf
            <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo ?? url()->previous()) }}">
            @include('branches.partials.form', ['submitLabel' => 'Guardar sucursal'])
        </form>
    </div>
</div>
@endsection