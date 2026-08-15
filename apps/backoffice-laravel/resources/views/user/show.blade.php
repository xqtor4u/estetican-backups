@php
    $screenDebugId = 'UsrSho';

    $page = \App\Support\Pages\UsersPage::show($user);
    $breadcrumbs = $page['breadcrumbs'];
    
    // Identificador de rol
    $roleLabel = match($user->role) {
        'admin' => 'Administrador',
        'operator' => 'Operador',
        default => ucfirst($user->role) ?? 'Usuario'
    };
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
        <a href="{{ route('users.edit', $user) }}" class="btn btn-primary">Editar usuario</a>
        
        @if(auth()->user()->is_super_admin && auth()->id() !== $user->id)
            <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger" data-confirm="¿Estás seguro de que deseas eliminar a este usuario? Si tiene historial asociado (citas, caja, actividad, etc.) se marcará como inactivo en vez de borrarse, para no perder ese historial.">Eliminar Usuario</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="row g-4 mt-1">
    {{-- Columna Izquierda: Identidad y Acceso --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    @if($user->profile_photo_path)
                        <img src="{{ asset('storage/' . $user->profile_photo_path) }}" alt="{{ $user->name }}" class="rounded-circle img-thumbnail app-avatar-lg shadow-sm">
                    @else
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center mx-auto app-avatar-lg shadow-sm">
                            <span class="fs-1 fw-bold text-primary">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        </div>
                    @endif
                </div>
                <h3 class="h5 mb-1">{{ $user->first_name }} {{ $user->last_name }}</h3>
                <p class="text-muted small mb-3">@ {{ $user->name }}</p>
                
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <span class="badge text-bg-primary rounded-pill px-3">{{ $roleLabel }}</span>
                    @if($user->is_operator)
                        <span class="badge text-bg-warning rounded-pill px-3">Tipo de Operador</span>
                    @endif
                </div>

                <hr class="my-4 opacity-10">

                <div class="text-start">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Acceso Backoffice</span>
                        <span class="badge {{ $user->can_login ? 'text-bg-success' : 'text-bg-danger' }} rounded-pill">
                            {{ $user->can_login ? 'Habilitado' : 'Denegado' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Estatus Laboral</span>
                        <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-pill">
                            {{ $user->is_active ? 'Activo' : 'Baja' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <h4 class="h6 mb-3 text-uppercase letter-space">Contacto</h4>
                <div class="mb-3">
                    <label class="d-block text-muted small">Correo electrónico</label>
                    <a href="mailto:{{ $user->email }}" class="text-decoration-none fw-semibold">{{ $user->email }}</a>
                </div>
                <div class="mb-3">
                    <label class="d-block text-muted small">Teléfono</label>
                    <span class="fw-semibold">{{ $user->phone ?: 'No registrado' }}</span>
                </div>
                <div>
                    <label class="d-block text-muted small">Dirección</label>
                    <p class="mb-0 small">{{ $user->address ?: 'Sin dirección registrada' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Columna Derecha: Detalles Expandidos --}}
    <div class="col-lg-8">
        {{-- Sección de Operador (April 14 Sprint) --}}
        @if($user->is_operator)
            <div class="card shadow-sm border-0 rounded-4 border-start border-warning border-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        </div>
                        <h4 class="h5 mb-0">Datos de Operador</h4>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Código Interno</label>
                            <span class="fw-bold font-monospace">{{ $user->operator_code ?: 'PENDIENTE' }}</span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Tipo de Operador</label>
                            <span class="fw-semibold text-primary">{{ $user->operatorRole?->name ?? 'Sin asignar' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-4">
                <h4 class="h6 mb-4 text-uppercase letter-space border-bottom pb-2">Información Legal y Seguridad</h4>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="text-muted small d-block">CURP / INE</label>
                        <span class="fw-semibold">{{ $user->ine_number ?: 'No registrado' }}</span>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small d-block">Número IMSS</label>
                        <span class="fw-semibold">{{ $user->imss_number ?: 'No registrado' }}</span>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small d-block">Fecha de Ingreso</label>
                        <span class="fw-semibold">{{ $user->hire_date ? $user->hire_date->format('d/m/Y') : 'No registrada' }}</span>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small d-block">Antigüedad</label>
                        <span class="fw-semibold">{{ $user->hire_date ? $user->hire_date->diffForHumans(null, true) : '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-4">
                <h4 class="h6 mb-4 text-uppercase letter-space border-bottom pb-2">Contacto de Emergencia</h4>
                @if($user->emergency_contact_name)
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Nombre del contacto</label>
                            <span class="fw-semibold text-danger">{{ $user->emergency_contact_name }}</span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Teléfono de emergencia</label>
                            <span class="fw-bold text-danger">{{ $user->emergency_contact_phone }}</span>
                        </div>
                    </div>
                @else
                    <div class="alert alert-light border-dashed text-center py-3">
                        <p class="text-muted mb-0 small">No hay información de contacto de emergencia registrada.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <h4 class="h6 mb-3 text-uppercase letter-space border-bottom pb-2">Notas Administrativas</h4>
                <div class="bg-light p-3 rounded-3">
                    <p class="mb-0 small text-dark">{{ $user->notes ?: 'Sin notas adicionales.' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection