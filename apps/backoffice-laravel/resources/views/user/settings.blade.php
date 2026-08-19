@php($screenDebugId = 'UsrSet')
@extends('layouts.app')

@section('title', 'Configuración de Usuario - ' . data_get(config('backoffice'), 'brand.kicker', 'EstetiCAN'))

@section('content')
<x-page-header
    eyebrow="Mi Perfil"
    title="Configuración personal"
    subtitle="Gestiona tu información, foto de perfil y seguridad de acceso."
>
    <x-slot:actions>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">Volver al inicio</a>
    </x-slot:actions>
</x-page-header>

<div class="container-fluid py-4">
    <div class="row g-4">
        {{-- Columna Izquierda: Información General --}}
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="card-title mb-0 h6 text-uppercase letter-spacing-1 fw-bold text-secondary">Información del Perfil</h5>
                </div>
                <div class="card-body p-4 pt-2">
                    <form action="{{ route('user.settings.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        
                        <div class="d-flex flex-column flex-md-row align-items-center gap-4 mb-4 pb-4 border-bottom">
                            <div class="profile-photo-wrapper">
                                <x-image-upload name="profile_photo" :value="$user->profile_photo_path" />
                            </div>
                            <div class="flex-grow-1 text-center text-md-start">
                                <h6 class="mb-1 fw-bold">{{ $user->first_name }} {{ $user->last_name }}</h6>
                                <p class="text-muted small mb-0">{{ $user->email }}</p>
                                <span class="badge rounded-pill bg-light text-dark border mt-2">
                                    {{ $user->is_super_admin ? 'Administrador Maestro' : ($user->role === 'admin' ? 'Administrador' : 'Operador') }}
                                </span>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary">Nombre de usuario (Login)</label>
                                <input type="text" name="name" class="form-control rounded-3" value="{{ old('name', $user->name) }}" required>
                                <small class="text-muted" style="font-size: 0.7rem;">Es el identificador para iniciar sesión.</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary">Nombre(s)</label>
                                <input type="text" name="first_name" class="form-control rounded-3" value="{{ old('first_name', $user->first_name) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary">Apellido paterno</label>
                                <input type="text" name="apellido_paterno" class="form-control rounded-3" value="{{ old('apellido_paterno', $user->apellido_paterno) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-secondary">Apellido materno</label>
                                <input type="text" name="apellido_materno" class="form-control rounded-3" value="{{ old('apellido_materno', $user->apellido_materno) }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-bold text-secondary">Correo electrónico</label>
                                <input type="email" name="email" class="form-control rounded-3" value="{{ old('email', $user->email) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-secondary">Teléfono</label>
                                <input type="text" name="phone" class="form-control rounded-3" value="{{ old('phone', $user->phone) }}">
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4 rounded-pill fw-bold shadow-sm">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Matriz de Permisos (Solo Lectura) --}}
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="card-title mb-0 h6 text-uppercase letter-spacing-1 fw-bold text-secondary">Mis Accesos y Permisos</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <p class="text-muted small mb-4">Esta matriz muestra las capacidades asignadas a tu cuenta por el administrador del sistema.</p>
                    
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light opacity-75">
                                <tr>
                                    <th>Módulo / Ventana</th>
                                    @foreach($actions as $label)
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
                                            <?php
                                                $permName = "{$actionKey} {$moduleKey}";
                                                $hasPerm = $user->is_super_admin || $user->hasPermissionTo($permName);
                                            ?>
                                            <td class="text-center">
                                                @if($hasPerm)
                                                    <span class="text-success">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                                            <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.317 8.394a.734.734 0 0 1 .012-1.049.733.733 0 0 1 1.046.012l2.455 2.457 5.906-5.844a.05.05 0 0 1 .035-.015h.001z"/>
                                                        </svg>
                                                    </span>
                                                @else
                                                    <span class="text-light opacity-25">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                                                        </svg>
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Columna Derecha: Seguridad y Datos Técnicos --}}
        <div class="col-lg-4">
            {{-- Tarjeta Seguridad --}}
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="card-title mb-0 h6 text-uppercase letter-spacing-1 fw-bold text-secondary">Seguridad de Acceso</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <form action="{{ route('user.settings.password') }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Contraseña actual</label>
                            <input type="password" name="current_password" class="form-control rounded-3" required>
                            @error('current_password') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nueva contraseña</label>
                            <input type="password" name="password" class="form-control rounded-3" required>
                            @error('password') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Confirmar nueva contraseña</label>
                            <input type="password" name="password_confirmation" class="form-control rounded-3" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark rounded-pill fw-bold shadow-sm">Actualizar contraseña</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tarjeta Bloqueo de Pantalla --}}
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="card-title mb-0 h6 text-uppercase letter-spacing-1 fw-bold text-secondary">Bloqueo de Pantalla</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <p class="text-muted small mb-3">Minutos de inactividad antes de bloquear tu sesión automáticamente. Solo tu contraseña la desbloquea. "Nunca" desactiva el bloqueo por completo.</p>
                    <form action="{{ route('user.settings.preferences') }}" method="POST">
                        @csrf
                        @method('PUT')

                        @php($currentIdleMinutes = (int) old('screen_lock_idle_minutes', $user->screen_lock_idle_minutes ?? config('backoffice.security.screen_lock_idle_minutes', 15)))

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Minutos de inactividad</label>
                            <select name="screen_lock_idle_minutes" class="form-select rounded-3" required>
                                @if (! in_array($currentIdleMinutes, [1, 2, 5, 10, 15, 30, 0], true))
                                    <option value="{{ $currentIdleMinutes }}" selected>{{ $currentIdleMinutes }} min (valor anterior)</option>
                                @endif
                                @foreach ([1, 2, 5, 10, 15, 30, 0] as $minutes)
                                    <option value="{{ $minutes }}" @selected($currentIdleMinutes === $minutes)>
                                        {{ $minutes === 0 ? 'Nunca' : "{$minutes} min" }}
                                    </option>
                                @endforeach
                            </select>
                            @error('screen_lock_idle_minutes') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-secondary rounded-pill fw-bold">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tarjeta Datos Técnicos (Solo lectura) --}}
            <div class="card shadow-sm border-0 rounded-4 bg-light border-start border-primary border-4">
                <div class="card-body p-4">
                    <h5 class="h6 text-uppercase letter-spacing-1 fw-bold text-primary mb-4">Ficha Técnica Operativa</h5>
                    
                    <div class="mb-3 pb-3 border-bottom">
                        <label class="d-block small text-muted text-uppercase fw-bold mb-1">Código de Operador</label>
                        <span class="font-monospace h6 mb-0">{{ $user->operator_code ?: 'N/A' }}</span>
                    </div>

                    <div class="mb-3 pb-3 border-bottom">
                        <label class="d-block small text-muted text-uppercase fw-bold mb-1">CURP / INE</label>
                        <span class="h6 mb-0">{{ $user->ine_number ?: 'Sin registro' }}</span>
                    </div>

                    <div class="mb-3 pb-3 border-bottom">
                        <label class="d-block small text-muted text-uppercase fw-bold mb-1">Número IMSS</label>
                        <span class="h6 mb-0">{{ $user->imss_number ?: 'Sin registro' }}</span>
                    </div>

                    <div class="mb-0">
                        <label class="d-block small text-muted text-uppercase fw-bold mb-1">Fecha de Ingreso</label>
                        <span class="h6 mb-0">{{ $user->hire_date ? $user->hire_date->format('d M, Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>
            
            <p class="text-center mt-4 text-muted small px-4">
                Si detectas algún error en tu ficha técnica, por favor contacta al administrador para su corrección.
            </p>
        </div>
    </div>
</div>
@endsection
