@php($screenDebugId = 'FinDsInd')
@extends('layouts.app')

@section('content')
<x-page-header
    eyebrow="Finanzas"
    title="Series de documentos"
    subtitle="Controla el foliado correlativo de recibos, facturas y notas. El próximo número se asigna de forma atómica."
>
    <x-slot:actions>
        <a href="{{ route('finances.document-series.create') }}" class="btn btn-primary">Nueva serie</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th style="width:120px">Tipo</th>
                        <th style="width:110px">Prefijo</th>
                        <th style="width:110px">Ejemplo</th>
                        <th style="width:110px">Próximo</th>
                        <th style="width:120px">Sucursal</th>
                        <th style="width:100px">Estado</th>
                        <th class="text-end" style="width:130px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($series as $s)
                        @php($example = $s->prefix . str_pad($s->next_number, $s->padding, '0', STR_PAD_LEFT) . $s->suffix)
                        <tr class="{{ $s->is_active ? '' : 'table-secondary opacity-60' }}">
                            <td class="fw-semibold">{{ $s->name }}</td>
                            <td>
                                <span class="badge rounded-pill text-bg-secondary">{{ $typeLabels[$s->document_type] ?? $s->document_type }}</span>
                            </td>
                            <td><code>{{ $s->prefix }}</code>{{ $s->suffix ? '<code>' . $s->suffix . '</code>' : '' }}</td>
                            <td><code class="text-primary">{{ $example }}</code></td>
                            <td class="text-center">{{ $s->next_number }}</td>
                            <td>{{ $s->branch?->name ?? '— Global —' }}</td>
                            <td>
                                <span class="catalog-status-badge {{ $s->is_active ? 'catalog-status-badge--active' : 'catalog-status-badge--inactive' }}">
                                    {{ $s->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('finances.document-series.edit', $s) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                                    <form action="{{ route('finances.document-series.destroy', $s) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            data-confirm="¿Eliminar la serie {{ $s->name }}?">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-body-secondary">No hay series de documentos configuradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
