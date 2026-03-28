@props(['screenDebugId' => null])

@php
    use App\Support\Navigation\MainNavigation;

    $backofficeConfig = config('backoffice');
    $navigationGroups = MainNavigation::groups();
    $mobileLinks = MainNavigation::mobileLinks();
    $showScreenDebugIds = (bool) data_get($backofficeConfig, 'ui.show_screen_debug_ids', false);
@endphp

<nav class="app-nav sticky-top border-bottom">
    <div class="container-fluid app-shell">
        <div class="app-nav-layout">
            <a class="navbar-brand d-flex flex-column gap-0 me-lg-3" href="{{ route('home') }}">
                <span class="brand-kicker">{{ data_get($backofficeConfig, 'brand.kicker', 'EstetiCAN') }}</span>
                <span class="brand-title-row">
                    <span class="brand-title">{{ data_get($backofficeConfig, 'brand.shell_title', 'Backoffice operativo') }}</span>
                    @if($showScreenDebugIds && $screenDebugId)
                        <span class="brand-screen-id" title="ID técnico de módulo y pantalla">{{ $screenDebugId }}</span>
                    @endif
                </span>
            </a>

            <details class="app-mobile-menu d-lg-none w-100">
                <summary class="app-mobile-menu-trigger">
                    <span class="app-mobile-menu-icon" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                    <span>{{ data_get($backofficeConfig, 'shell.mobile_menu_label', 'Menú') }}</span>
                </summary>

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
                            <div class="app-dropdown-label">{{ $group['label'] }}</div>

                            @foreach($group['items'] as $item)
                                @if($item['route'])
                                    <a href="{{ $item['route'] }}" class="dropdown-item app-dropdown-item {{ $item['active'] ? 'active' : '' }}">
                                        <span class="app-dropdown-title">{{ $item['label'] }}</span>
                                        <small class="app-dropdown-description">{{ $item['description'] }}</small>
                                    </a>
                                @else
                                    <div class="dropdown-item app-dropdown-item disabled {{ $item['active'] ? 'active' : '' }}">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <span class="app-dropdown-title">{{ $item['label'] }}</span>
                                                <small class="app-dropdown-description">{{ $item['description'] }}</small>
                                            </div>
                                            @if(!empty($item['comingSoon']))
                                                <span class="badge rounded-pill text-bg-light">Próx.</span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
            </div>
        </div>
    </div>
</nav>