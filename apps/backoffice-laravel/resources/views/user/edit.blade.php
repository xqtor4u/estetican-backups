@php $screenDebugId = 'UsrEdi'; @endphp
@extends('layouts.app')

@section('title', 'Editar Usuario - ' . data_get(config('backoffice'), 'brand.kicker', 'EstetiCAN'))

@php
    $page = \App\Support\Pages\UsersPage::edit($user);
    $breadcrumbs = $page['breadcrumbs'];
@endphp

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('users.show', $user) }}" class="btn btn-outline-secondary">Cancelar</a>
    </x-slot:actions>
</x-page-header>

@if(session('success'))
    <div class="alert alert-success mt-3">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger mt-3">
        <strong>Revisa los errores:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('users.update', $user->id) }}" class="mt-4" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- Columna: Identidad --}}
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <h4 class="h6 mb-4 text-uppercase letter-space border-bottom pb-2">Identidad y Autenticación</h4>

                    <div class="d-flex justify-content-center mb-4">
                        <x-image-upload name="profile_photo" :value="$user->profile_photo_path" />
                    </div>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="name" class="form-label">Nombre de usuario (login)</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                            <div class="form-text">Debe ser único. Se usa para iniciar sesión.</div>
                        </div>
                        <div class="col-md-3">
                            <label for="first_name" class="form-label">Nombre(s)</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}">
                        </div>
                        <div class="col-md-3">
                            <label for="apellido_paterno" class="form-label">Apellido paterno</label>
                            <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="{{ old('apellido_paterno', $user->apellido_paterno) }}">
                        </div>
                        <div class="col-md-3">
                            <label for="apellido_materno" class="form-label">Apellido materno</label>
                            <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="{{ old('apellido_materno', $user->apellido_materno) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Cambiar contraseña</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Dejar en blanco para no cambiar">
                                <button class="btn btn-outline-secondary" type="button" data-password-toggle="password">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                                    <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                                </svg>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                                <button class="btn btn-outline-secondary" type="button" data-password-toggle="password_confirmation">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                                    <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                                </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <h4 class="h6 mb-4 text-uppercase letter-space border-bottom pb-2">Información Legal y Seguridad</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="ine_number" class="form-label">CURP / INE</label>
                            <input type="text" class="form-control" id="ine_number" name="ine_number" value="{{ old('ine_number', $user->ine_number) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="imss_number" class="form-label">Número IMSS</label>
                            <input type="text" class="form-control" id="imss_number" name="imss_number" value="{{ old('imss_number', $user->imss_number) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="hire_date" class="form-label">Fecha de ingreso</label>
                            <input type="date" class="form-control" id="hire_date" name="hire_date" value="{{ old('hire_date', $user->hire_date ? $user->hire_date->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <h4 class="h6 mb-4 text-uppercase letter-space border-bottom pb-2">Contacto de Emergencia</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="emergency_contact_name" class="form-label">Nombre de contacto</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $user->emergency_contact_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="emergency_contact_phone" class="form-label">Teléfono de emergencia</label>
                            <input type="text" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $user->emergency_contact_phone) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h4 class="h6 mb-3 text-uppercase letter-space">Notas y Observaciones</h4>
                    <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $user->notes) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Columna: Control de Acceso y Operador --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <h4 class="h6 mb-4 text-uppercase letter-space border-bottom pb-2">Acceso al Sistema</h4>
                    
                    <div class="mb-4">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="can_login" value="0">
                            <input class="form-check-input" type="checkbox" id="can_login" name="can_login" value="1" @checked(old('can_login', $user->can_login))>
                            <label class="form-check-label fw-bold" for="can_login">Permitir acceso al Backoffice</label>
                        </div>
                        <small class="text-muted d-block lh-sm">Si se desactiva, el usuario no podrá iniciar sesión aunque su contraseña sea correcta.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label d-block small fw-bold">Rol Administrativo</label>
                        <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="role" id="role_admin" value="admin" {{ ($user->role ?? '') === 'admin' ? 'checked' : '' }}>
                                <label class="form-check-label" for="role_admin">Administrador</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="role" id="role_operator" value="operator" {{ (($user->role ?? '') === 'operator' || !in_array($user->role, ['admin', 'operator'])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="role_operator">Operador / Técnico</label>
                            </div>
                    </div>

                    <div class="mb-0">
                        <label for="is_active" class="form-label small fw-bold">Estado del Empleado</label>
                        <select class="form-select" id="is_active" name="is_active">
                            <option value="1" @selected(old('is_active', $user->is_active))>Empleado Activo</option>
                            <option value="0" @selected(!old('is_active', $user->is_active))>Baja / Inactivo</option>
                        </select>
                        <small class="text-muted d-block mt-2 lh-sm">Los empleados inactivos no aparecen en selectores de agenda ni listas operativas.</small>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4 border-start border-warning border-4">
                <div class="card-body p-4">
                    <h4 class="h6 mb-4 text-uppercase letter-space border-bottom pb-2 text-warning">Perfil de Operador</h4>
                    
                    <div class="mb-4">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="is_operator" value="0">
                            <input class="form-check-input" type="checkbox" id="is_operator" name="is_operator" value="1" @checked(old('is_operator', $user->is_operator))>
                            <label class="form-check-label fw-bold" for="is_operator">¿Es personal operativo?</label>
                        </div>
                        <small class="text-muted d-block lh-sm">Habilita campos de código técnico y rol de ejecución (e.g. Groomer).</small>
                    </div>

                    <div class="mb-3">
                        <label for="operator_code" class="form-label">Código de operador</label>
                        <input type="text" class="form-control font-monospace" id="operator_code" name="operator_code" value="{{ old('operator_code', $user->operator_code) }}" placeholder="Ej: GRO-001">
                    </div>

                    <div class="mb-3">
                        <label for="operator_role_id" class="form-label">Tipo de Operador</label>
                        <select class="form-select" id="operator_role_id" name="operator_role_id">
                            <option value="">Sin asignar</option>
                            @foreach($operatorRoles as $role)
                                <option value="{{ $role->id }}" @selected(old('operator_role_id', $user->operator_role_id) == $role->id)>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($user->operator_id)
                        <div class="alert alert-success py-2 px-3 mb-0 small">
                            Vinculado al operador <strong>#{{ $user->operator_id }}</strong>
                            — los cambios aquí se sincronizan automáticamente.
                        </div>
                    @elseif($user->is_operator)
                        <div class="alert alert-warning py-2 px-3 mb-0 small">
                            Sin registro de operador aún. Se creará al guardar.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <h4 class="h6 mb-4 text-uppercase letter-space border-bottom pb-2">Matriz de Permisos (CRUD)</h4>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Módulo / Ventana</th>
                                    @foreach($actions as $action => $label)
                                        <th class="text-center">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($modules as $moduleKey => $module)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-secondary small">{{ $module['label'] }}</div>
                                            <div class="text-muted" style="font-size: 0.65rem; font-family: monospace;">[{{ $module['code'] }}]</div>
                                        </td>
                                        @foreach($actions as $actionKey => $actionLabel)
                                            @php
                                                $permName = "{$actionKey} {$moduleKey}";
                                            @endphp
                                            <td class="text-center">
                                                <input type="checkbox" 
                                                       name="permissions[]" 
                                                       value="{{ $permName }}" 
                                                       class="form-check-input"
                                                       @checked(in_array($permName, $userPermissions))>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">Nota: Los administradores tienen todos los permisos habilitados por defecto mediante su rol.</small>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg shadow-sm">Guardar Cambios</button>
                <a href="{{ route('users.index') }}" class="btn btn-link text-muted">Cancelar y volver</a>
            </div>
        </div>
    </div>
</form>
@endsection
