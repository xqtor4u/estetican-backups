@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('whatsapp.bandeja') }}" class="btn btn-outline-secondary">Bandeja diaria</a>
        <a href="{{ route('whatsapp.recurrencias') }}" class="btn btn-outline-secondary">Recurrencias</a>
        <a href="{{ route('whatsapp.plantillas.create') }}" class="btn btn-primary">Nueva plantilla</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th style="width:130px">Contexto</th>
                        <th>Mensaje</th>
                        <th style="width:100px">Usos</th>
                        <th style="width:100px">Estado</th>
                        <th class="text-end" style="width:160px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $template)
                        <tr>
                            <td class="fw-semibold">{{ $template->name }}</td>
                            <td>
                                @if($template->context === 'recurrencia')
                                    <span class="badge bg-info-subtle text-info-emphasis">Recurrencias</span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary-emphasis">Bandeja diaria</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($template->body, 80) }}</td>
                            <td>{{ $template->messages_count + $template->recurrence_messages_count }}</td>
                            <td>
                                @if($template->is_active)
                                    <span class="badge bg-success-subtle text-success-emphasis">Activa</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('whatsapp.plantillas.edit', $template) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                <form action="{{ route('whatsapp.plantillas.destroy', $template) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar esta plantilla?">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Aún no hay plantillas de mensaje.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
