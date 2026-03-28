@php($backofficeConfig = config('backoffice'))
@php($useHotReload = (bool) data_get($backofficeConfig, 'assets.use_hot_reload', false))
@php($hasHotFile = file_exists(public_path('hot')))
@php($hasBuildManifest = file_exists(public_path('build/manifest.json')))
@php($viteHotFile = $useHotReload ? public_path('hot') : storage_path('framework/vite.hot.disabled'))
@php($appDensity = data_get($backofficeConfig, 'ui.density', 'comfortable'))
@php($appPalette = data_get($backofficeConfig, 'ui.palette', 'earth-clinic'))
@php($currentScreenDebugId = $page['screen_id'] ?? ($screenDebugId ?? null))
@php(\Illuminate\Support\Facades\Vite::useHotFile($viteHotFile))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ data_get($backofficeConfig, 'brand.html_title', 'EstetiCAN Backoffice') }}</title>
    <link rel="icon" type="image/webp" href="{{ asset(data_get($backofficeConfig, 'brand.favicon', 'favicon.webp')) }}">
    @if(($useHotReload && $hasHotFile) || $hasBuildManifest)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/backoffice-app.css') }}">
        <link rel="stylesheet" href="{{ asset('css/backoffice-blueprints.css') }}">
    @endif
    @foreach(data_get($backofficeConfig, 'fonts.preconnect', []) as $fontPreconnect)
        <link rel="preconnect" href="{{ $fontPreconnect }}" @if(str_contains($fontPreconnect, 'gstatic')) crossorigin @endif>
    @endforeach
    <link href="{{ data_get($backofficeConfig, 'fonts.stylesheet_url') }}" rel="stylesheet">
</head>
<body
    class="app-density-{{ $appDensity }}"
    data-theme-palette="{{ $appPalette }}"
    data-confirm-actions-enabled="{{ data_get($backofficeConfig, 'security.confirm_destructive_actions', true) ? 'true' : 'false' }}"
    data-resizable-tables-enabled="{{ data_get($backofficeConfig, 'ui.enable_resizable_tables', true) ? 'true' : 'false' }}"
    data-address-default-country="{{ e((string) data_get($backofficeConfig, 'system.default_address_country', 'México')) }}"
    data-address-default-state="{{ e((string) data_get($backofficeConfig, 'system.default_address_state', '')) }}"
    data-address-default-city="{{ e((string) data_get($backofficeConfig, 'system.default_address_city', '')) }}"
>
    <x-main-navigation :screen-debug-id="$currentScreenDebugId" />

    <main class="container-fluid app-shell app-main">
        @if(isset($breadcrumbs) && count($breadcrumbs) > 0)
            <x-breadcrumbs :items="$breadcrumbs" />
        @endif

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Revisa los datos capturados.</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @unless(($useHotReload && $hasHotFile) || $hasBuildManifest)
        <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    @endunless
    @stack('scripts')
</body>
</html>