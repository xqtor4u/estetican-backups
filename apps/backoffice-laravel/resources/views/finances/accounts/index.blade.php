@php($screenDebugId = 'FinAccInd')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas"
    title="Catálogo de cuentas"
    subtitle="Árbol jerárquico de cuentas contables. Las cuentas agrupadora no admiten movimientos directos."
>
    <x-slot:actions>
        <a href="{{ route('finances.accounts.create') }}" class="btn btn-primary">Nueva cuenta</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">

    {{-- Resumen --}}
    <section class="catalog-overview mb-4">
        <div class="catalog-overview__grid">
            <article class="catalog-overview-card catalog-overview-card--primary">
                <span class="catalog-overview-card__eyebrow">Total de cuentas</span>
                <div class="catalog-overview-card__value">{{ $totals['total'] }}</div>
                <p class="catalog-overview-card__text">Incluyendo cuentas agrupadora y subcuentas operativas.</p>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Cuentas activas</span>
                <div class="catalog-overview-card__value">{{ $totals['active'] }}</div>
                <p class="catalog-overview-card__text">Disponibles para selección en servicios y métodos de pago.</p>
            </article>
            <article class="catalog-overview-card">
                <span class="catalog-overview-card__eyebrow">Admiten movimientos</span>
                <div class="catalog-overview-card__value">{{ $totals['entries'] }}</div>
                <p class="catalog-overview-card__text">Subcuentas de nivel hoja donde se registran débitos y créditos.</p>
            </article>
        </div>
    </section>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Árbol de cuentas --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:130px">Código</th>
                        <th>Nombre</th>
                        <th style="width:110px">Tipo</th>
                        <th style="width:110px">Estado</th>
                        <th style="width:130px">Mov. directos</th>
                        <th class="text-end" style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roots as $root)
                        @include('finances.accounts._tree_row', ['account' => $root, 'depth' => 0])
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
