@props(['screenDebugId' => null])

@php

    $backofficeConfig = config('backoffice');
    $navigationGroups = \App\Support\Navigation\MainNavigation::groups();
    $mobileLinks = \App\Support\Navigation\MainNavigation::mobileLinks();
    $showScreenDebugIds = (bool) data_get($backofficeConfig, 'ui.show_screen_debug_ids', false);
@endphp

<nav class="app-nav sticky-top border-bottom">
    <div class="container-fluid app-shell">
        <div class="app-nav-layout">
            <div class="d-flex align-items-center me-lg-3">
                <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                    @if(data_get($backofficeConfig, 'brand.logo'))
                        <img src="{{ Storage::disk('public')->url(data_get($backofficeConfig, 'brand.logo')) }}" alt="Logo" style="height: 32px; width: auto; object-fit: contain;">
                    @endif
                    <div class="d-flex flex-column gap-0">
                        <span class="brand-kicker">
                            {{ data_get($backofficeConfig, 'brand.kicker', 'EstetiCAN') }}
                            <span class="ms-1 opacity-50 small fw-normal" style="font-size: 0.75rem;">v.{{ data_get($backofficeConfig, 'brand.version', '000000-0000') }}</span>
                        </span>
                        <span class="brand-title-row">
                            <span class="brand-title">{{ data_get($backofficeConfig, 'brand.shell_title', 'Backoffice operativo') }}</span>
                        </span>
                    </div>
                </a>
                @if($showScreenDebugIds && $screenDebugId)
                    <span class="brand-screen-id ms-2" title="ID técnico de módulo y pantalla (Haz click para seleccionar y copiar)" style="user-select: all; cursor: text; pointer-events: auto; display: inline-block; vertical-align: middle;">
                        {{ $screenDebugId }}
                    </span>
                @endif

                {{-- Reloj en vivo del servidor, en su zona horaria configurada — para poder
                verificar de un vistazo que el server está a tiempo (ver NT del bug de zona
                horaria: app.timezone estuvo en UTC en vez de America/Mexico_City). --}}
                <span
                    id="app-server-clock"
                    class="app-server-clock ms-2 d-none d-sm-inline-flex align-items-center gap-1"
                    data-epoch="{{ now()->timestamp }}"
                    data-tz="{{ config('app.timezone') }}"
                    title="Hora actual del servidor, en su zona horaria configurada ({{ config('app.timezone') }}) — compárala con tu hora real"
                    style="font-size: 0.75rem; font-variant-numeric: tabular-nums; opacity: 0.75; white-space: nowrap;"
                >
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    <span class="app-server-clock__text">—</span>
                </span>
            </div>


            <details class="app-mobile-menu d-lg-none w-100">
                <summary class="app-mobile-menu-trigger d-flex align-items-center gap-2">
                    <span class="app-mobile-menu-icon" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                    <span>{{ data_get($backofficeConfig, 'shell.mobile_menu_label', 'Menú') }}</span>
                    @auth
                        <span class="app-mobile-avatar ms-auto" style="width: 2.2em; height: 2.2em; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: #e0e0e0; font-weight: bold; font-size: 1.2em; color: #555; overflow: hidden;">
                            @if(auth()->user()->profile_photo_path)
                                <img src="{{ asset('storage/' . auth()->user()->profile_photo_path) }}" alt="avatar" style="width:100%;height:100%;object-fit:cover;">
                            @else
                                {{ strtoupper(mb_substr(auth()->user()->name,0,1)) }}
                            @endif
                        </span>
                    @endauth
                </summary>

                @auth
                <div class="app-mobile-nav-section mt-2">
                    <span class="app-mobile-nav-label">Cuenta</span>
                    <div class="app-mobile-nav-grid mb-2">
                        <a href="{{ route('user.settings') }}" class="app-mobile-link">Configuración</a>
                        <form method="POST" action="{{ route('screen-lock.lock') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="redirect_url" value="{{ request()->getRequestUri() }}">
                            <button type="submit" class="app-mobile-link" style="background:none;border:none;padding:0;margin:0;">Bloquear pantalla</button>
                        </form>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="app-mobile-link" style="background:none;border:none;padding:0;margin:0;">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
                @endauth

                <div class="app-mobile-nav-section mt-2">
                    <span class="app-mobile-nav-label">Accesos</span>
                    <div class="app-mobile-nav-grid">
                        <a href="{{ route('home') }}" class="app-mobile-link {{ request()->routeIs('home') ? 'active' : '' }}">{{ data_get($backofficeConfig, 'shell.home_label', 'Inicio') }}</a>
                        @foreach($mobileLinks as $mobileLink)
                            <a href="{{ $mobileLink['route'] }}" class="app-mobile-link {{ $mobileLink['active'] ? 'active' : '' }}">{{ $mobileLink['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            </details>

            <div class="app-desktop-nav d-none d-lg-flex">
            <ul class="app-nav-list mb-0">
                <li class="nav-item">
                    <a href="{{ route('home') }}" class="nav-link app-nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
                        {{ data_get($backofficeConfig, 'shell.home_label', 'Inicio') }}
                    </a>
                </li>

                @foreach($navigationGroups as $group)
                    <li class="nav-item dropdown app-nav-dropdown {{ $group['active'] ? 'show' : '' }}">
                        <a
                            href="#"
                            class="nav-link app-nav-link dropdown-toggle {{ $group['active'] ? 'active' : '' }}"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            aria-expanded="false"
                        >
                            {{ $group['label'] }}
                        </a>

                        <div class="dropdown-menu app-dropdown-menu dropdown-menu-end">
                            @if(isset($group['subgroups']))
                                @foreach($group['subgroups'] as $subgroup)
                                    @if(!$loop->first)
                                        <hr class="dropdown-divider">
                                    @endif
                                    <div class="app-dropdown-label">{{ $subgroup['label'] }}</div>
                                    @foreach($subgroup['items'] as $item)
                                        <x-main-navigation-item :item="$item" />
                                    @endforeach
                                @endforeach
                            @else
                                <div class="app-dropdown-label">{{ $group['label'] }}</div>
                                @foreach($group['items'] as $item)
                                    <x-main-navigation-item :item="$item" />
                                @endforeach
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <ul class="app-nav-list mb-0 ms-3">
                @auth
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link app-nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="app-desktop-avatar" style="width:2.2em;height:2.2em;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:#e0e0e0;font-weight:bold;font-size:1.2em;color:#555;overflow:hidden;">
                                @if(auth()->user()->profile_photo_path)
                                    <img src="{{ asset('storage/' . auth()->user()->profile_photo_path) }}" alt="avatar" style="width:100%;height:100%;object-fit:cover;">
                                @else
                                    {{ strtoupper(mb_substr(auth()->user()->name,0,1)) }}
                                @endif
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="{{ route('user.settings') }}" class="dropdown-item">Configuración</a>
                            <form method="POST" action="{{ route('screen-lock.lock') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="redirect_url" value="{{ request()->getRequestUri() }}">
                                <button type="submit" class="dropdown-item">Bloquear pantalla</button>
                            </form>
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="dropdown-item">Cerrar sesión</button>
                            </form>
                        </div>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<script nonce="{{ csp_nonce() }}">
(function () {
    var el = document.getElementById('app-server-clock');
    if (!el) return;
    var textEl = el.querySelector('.app-server-clock__text');
    var baseEpochMs = parseInt(el.dataset.epoch, 10) * 1000;
    var tz = el.dataset.tz;
    var loadedAt = Date.now();
    var formatter = new Intl.DateTimeFormat('es-MX', {
        timeZone: tz,
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        hour12: false,
    });
    function tick() {
        var now = new Date(baseEpochMs + (Date.now() - loadedAt));
        textEl.textContent = formatter.format(now) + ' (' + tz + ')';
    }
    tick();
    setInterval(tick, 1000);
})();
</script>