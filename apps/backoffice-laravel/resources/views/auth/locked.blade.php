@php
    $backofficeConfig = config('backoffice');
    $useHotReload = (bool) data_get($backofficeConfig, 'assets.use_hot_reload', false);
    $hasHotFile = file_exists(public_path('hot'));
    $hasBuildManifest = file_exists(public_path('build/manifest.json'));
    $viteHotFile = $useHotReload ? public_path('hot') : storage_path('framework/vite.hot.disabled');
    \Illuminate\Support\Facades\Vite::useHotFile($viteHotFile);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión bloqueada - {{ data_get($backofficeConfig, 'brand.html_title', 'EstetiCAN Backoffice') }}</title>
    @if(($useHotReload && $hasHotFile) || $hasBuildManifest)
        @vite(['resources/css/app.css'])
    @else
        <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/backoffice-app.css') }}">
        <link rel="stylesheet" href="{{ asset('css/backoffice-theme.css') }}">
    @endif
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-4">
                <div class="card shadow rounded-4">
                    <div class="card-body p-4 text-center">
                        <div class="mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="text-secondary" viewBox="0 0 16 16">
                                <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                            </svg>
                        </div>
                        <h2 class="h4 mb-1">Sesión bloqueada</h2>
                        <p class="text-muted small mb-4">Bloqueada por {{ $lockedByName }}</p>

                        <form method="POST" action="{{ route('screen-lock.unlock') }}" class="text-start">
                            @csrf
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" name="password" id="password" class="form-control" required autofocus>
                                @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Desbloquear</button>
                        </form>

                        <form method="POST" action="{{ route('logout') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm text-muted">¿No eres tú? Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
