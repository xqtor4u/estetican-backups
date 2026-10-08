@php
    $screenDebugId = 'DiaInh';

    $page = \App\Support\Pages\BranchesPage::nonWorkingDays();
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
/>

@can('crear sucursales')
    <div class="card mb-3">
        <div class="card-body">
            <form action="{{ route('non-working-days.store') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-3">
                    <x-form-label for="date" :required="true">Fecha</x-form-label>
                    <input id="date" type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date') }}" required>
                    @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <x-form-label for="reason" :required="true">Motivo</x-form-label>
                    <input id="reason" type="text" name="reason" maxlength="120" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" placeholder="Ej. Navidad, inventario anual" required>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <x-form-label for="branch_id">Aplica a</x-form-label>
                    <select id="branch_id" name="branch_id" class="form-select">
                        <option value="">Todas las sucursales</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Agregar</button>
                </div>
            </form>
        </div>
    </div>
@endcan

<div class="d-flex justify-content-end mb-2">
    @if($showPast)
        <a href="{{ route('non-working-days.index') }}" class="small">Ver solo los próximos</a>
    @else
        <a href="{{ route('non-working-days.index', ['past' => 1]) }}" class="small">Incluir los que ya pasaron</a>
    @endif
</div>

<x-list-table :paginator="$days">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Motivo</th>
            <th>Aplica a</th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($days as $day)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $day->date->translatedFormat('D d/m/Y') }}</div>
                    <div class="small text-body-secondary">{{ $day->date->isPast() && ! $day->date->isToday() ? 'Ya pasó' : $day->date->diffForHumans() }}</div>
                </td>
                <td>{{ $day->reason }}</td>
                <td>{{ $day->branch?->name ?? 'Todas las sucursales' }}</td>
                <td class="text-end">
                    @can('eliminar sucursales')
                        <form action="{{ route('non-working-days.destroy', $day) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar este día inhábil?">Eliminar</button>
                        </form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-center py-4 text-body-secondary">No hay días inhábiles próximos registrados.</td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>
@endsection
