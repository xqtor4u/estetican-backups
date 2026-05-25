@php
    $screenDebugId = 'UsrInd';
    $page = \App\Support\Pages\UsersPage::index();
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
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-plus me-1" viewBox="0 0 16 16"><path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H1s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C9.516 10.68 8.289 10 6 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/><path fill-rule="evenodd" d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5"/></svg>
            Alta de usuario
        </a>
    </x-slot:actions>
</x-page-header>

<div class="mt-4">
    <x-list-table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Identidad</th>
                <th>Acceso</th>
                <th>Perfil</th>
                <th title="Tipo de Operador">Tipo</th>
                <th>Rol Administrativo</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                <tr class="{{ !$user->is_active ? 'opacity-75 grayscale' : '' }}">
                    <td><span class="text-muted small">#{{ $user->id }}</span></td>
                    <td>
                        <div class="fw-bold">{{ $user->name }}</div>
                        <div class="text-body-secondary small">{{ $user->email }}</div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $user->first_name }} {{ $user->last_name }}</div>
                        <div class="text-body-secondary small">{{ $user->phone ?: 'Sin teléfono' }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $user->can_login ? 'text-bg-success' : 'text-bg-danger' }} rounded-pill">
                            {{ $user->can_login ? 'Habilitado' : 'Denegado' }}
                        </span>
                    </td>
                    <td>
                        @if($user->is_operator)
                            <span class="badge text-bg-warning rounded-pill">Técnico: {{ $user->operator_code }}</span>
                        @else
                            <span class="text-muted small">N/A</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge text-bg-light border px-2 py-1">
                            {{ $user->role === 'admin' ? 'Administrador' : 'Staff' }}
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="btn-group">
                            <a href="{{ route('users.show', $user->id) }}" class="btn btn-sm btn-outline-dark">Ver</a>
                            <a href="{{ route('users.edit', $user->id) }}" class="btn btn-sm btn-outline-dark">Editar</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="py-5">
                        <x-empty-state 
                            icon="bi-people-fill"
                            title="No hay usuarios registrados"
                            subtitle="Aún no has creado ningún perfil administrativo u operativo."
                            action-label="Crear mi primer usuario"
                            :action-route="route('users.create')"
                        />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-list-table>
</div>
@endsection
