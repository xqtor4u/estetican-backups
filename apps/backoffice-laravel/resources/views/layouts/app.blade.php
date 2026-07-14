@php
    $backofficeConfig = config('backoffice');
    $useHotReload = (bool) data_get($backofficeConfig, 'assets.use_hot_reload', false);
    $hasHotFile = file_exists(public_path('hot'));
    $hasBuildManifest = file_exists(public_path('build/manifest.json'));
    $viteHotFile = $useHotReload ? public_path('hot') : storage_path('framework/vite.hot.disabled');
    $appDensity = data_get($backofficeConfig, 'ui.density', 'comfortable');
    $appPalette = data_get($backofficeConfig, 'ui.palette', 'earth-clinic');
    $currentScreenDebugId = $page['screen_id'] ?? ($screenDebugId ?? null);
    \Illuminate\Support\Facades\Vite::useHotFile($viteHotFile);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', data_get($backofficeConfig, 'brand.html_title', 'EstetiCAN Backoffice'))</title>
    @php
        $faviconConfig = data_get($backofficeConfig, 'brand.favicon', 'favicon.webp');
        $faviconUrl = str_contains($faviconConfig, '/') 
            ? Storage::disk('public')->url($faviconConfig) 
            : asset($faviconConfig);
    @endphp
    <link rel="icon" href="{{ $faviconUrl }}">
    @if(($useHotReload && $hasHotFile) || $hasBuildManifest)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/backoffice-app.css') }}">
        <link rel="stylesheet" href="{{ asset('css/backoffice-blueprints.css') }}">
        <link rel="stylesheet" href="{{ asset('css/backoffice-theme.css') }}">
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
    data-time24h="{{ $timeFormat === 'H:i' ? '1' : '0' }}"
>
    <x-main-navigation :screen-debug-id="$currentScreenDebugId" />

    <main class="container-fluid app-shell app-main">
        @php
            $renderBreadcrumbs = $breadcrumbs ?? ($page['breadcrumbs'] ?? null);
        @endphp
        @if(isset($renderBreadcrumbs) && count($renderBreadcrumbs) > 0)
            <div class="sticky-top bg-body z-2 pb-1 mb-3 border-bottom" style="top: 60px; padding-top: 1rem;">
                <x-breadcrumbs :items="$renderBreadcrumbs" />
            </div>
        @endif

        <!-- Toast Container -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            @if(session('success'))
                <div id="toastSuccess" class="toast align-items-center text-white bg-success border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body p-3 fw-bold">
                            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div id="toastError" class="toast align-items-center text-white bg-danger border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body p-3 fw-bold">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            @endif

            @if(session('warning'))
                <div id="toastWarning" class="toast align-items-center text-dark bg-warning border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body p-3 fw-bold">
                            <i class="bi bi-geo-alt-fill me-2"></i> {{ session('warning') }}
                        </div>
                        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            @endif
        </div>

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

    <!-- Global Confirmation Modal -->
    <div class="modal fade" id="globalConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="confirmTitle">Confirmar acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p id="confirmMessage" class="mb-0 text-body-secondary"></p>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-dark rounded-pill px-4" id="confirmExecute">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    @unless(($useHotReload && $hasHotFile) || $hasBuildManifest)
        <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    @endunless

    <script nonce="{{ csp_nonce() }}">
        document.addEventListener('DOMContentLoaded', () => {
            // Config from PHP
            const toastDuration = {{ data_get($backofficeConfig, 'ui.toast_duration_ms', 5000) }};
            const tooltipDelay = {{ data_get($backofficeConfig, 'ui.tooltip_delay_ms', 1500) }};

            // Initialize Toasts
            const toastElList = [].slice.call(document.querySelectorAll('.toast'));
            const toastList = toastElList.map(toastEl => {
                const t = new bootstrap.Toast(toastEl, { autohide: true, delay: toastDuration });
                t.show();
                return t;
            });

            // Initialize Tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    delay: { "show": tooltipDelay, "hide": 100 }
                });
            });

            // Standardize Confirmations (UI Upgrade)
            const globalModal = new bootstrap.Modal(document.getElementById('globalConfirmModal'));
            const confirmBtn = document.getElementById('confirmExecute');
            const messageEl = document.getElementById('confirmMessage');
            let currentAction = null;

            window.premiumConfirm = (message, onConfirm) => {
                messageEl.textContent = message;
                currentAction = onConfirm;
                globalModal.show();
            };

            confirmBtn.addEventListener('click', () => {
                if (currentAction) currentAction();
                globalModal.hide();
            });
        });
    </script>
    @stack('scripts')
</body>
</html>