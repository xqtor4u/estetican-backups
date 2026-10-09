@php
    $screenDebugId = 'AgSerInd';

    $page = \App\Support\Pages\AgendaPage::seriesIndex();
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
        <a href="{{ route('agenda.index', ['por_fijar' => 1]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-pin-angle me-1"></i> Citas por fijar
        </a>
    </x-slot:actions>
</x-page-header>

<ul class="nav nav-tabs mb-3">
    @foreach($tabs as $key => $label)
        <li class="nav-item">
            <a href="{{ route('agenda.series.index', ['tab' => $key]) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}">
                {{ $label }} <span class="badge rounded-pill text-bg-light border ms-1">{{ $counts[$key] }}</span>
            </a>
        </li>
    @endforeach
</ul>

@if($tab === 'terminan')
    <p class="small text-body-secondary">Series cuya vigencia termina este mes. Habla con el cliente y, si sigue, ábrela y extiende la vigencia.</p>
@endif

<x-list-table :paginator="$series">
    <thead>
        <tr>
            <th>Mascota</th>
            <th>Repetición</th>
            <th>Vigencia</th>
            <th>Citas</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($series as $item)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $item->pet?->name ?? 'Mascota' }}</div>
                    <div class="small text-body-secondary">{{ $item->pet?->client?->full_name }}</div>
                </td>
                <td>
                    <span class="agenda-series-dot"></span>
                    {{ $item->recurrenceRule()->label() }} · {{ $item->starts_at->format('H:i') }}
                    @if($item->branch)<div class="small text-body-secondary">{{ $item->branch->name }}</div>@endif
                </td>
                <td>
                    <div>{{ $item->starts_at->format('d/m/Y') }} → {{ $item->ends_on->format('d/m/Y') }}</div>
                    @if($item->ends_on->gte(today()))
                        <div class="small text-body-secondary">termina {{ $item->ends_on->diffForHumans() }}</div>
                    @endif
                </td>
                <td>
                    <div>restan {{ $item->remaining_count }}</div>
                    @if($item->pending_pin_count > 0)
                        <div class="small text-body-secondary">{{ $item->pending_pin_count }} por fijar</div>
                    @endif
                </td>
                <td><span class="badge text-bg-light border">{{ $item->statusLabel() }}</span></td>
                <td class="text-end">
                    <a href="{{ route('agenda.series.show', $item) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center py-4 text-body-secondary">No hay series en esta pestaña.</td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>
@endsection
