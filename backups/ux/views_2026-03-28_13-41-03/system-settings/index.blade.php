@extends('layouts.app')

@php($breadcrumbs = $page['breadcrumbs'])

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="#ui" class="btn btn-outline-dark">Visualización</a>
        <a href="#system" class="btn btn-outline-dark">Sistema</a>
        <a href="#security" class="btn btn-outline-dark">Seguridad</a>
    </x-slot:actions>
</x-page-header>

<div class="catalog-content-wide">
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-2">Visualización activa</div>
                    <div class="h5 mb-1" data-active-palette-label>{{ $sections['ui']['fields']['ui_color_palette']['selectedOption']['label'] ?? 'Tierra clínica' }}</div>
                    <div class="text-body-secondary small">Densidad <span data-active-density-label>{{ $sections['ui']['fields']['ui_density']['value'] === 'compact' ? 'compacta' : 'cómoda' }}</span> · branding, shell y ergonomía viven en la misma fuente operativa.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-2">Runtime base</div>
                    <div class="h5 mb-1">{{ $sections['system']['fields']['system_timezone']['value'] }}</div>
                    <div class="text-body-secondary small">Locale {{ strtoupper($sections['system']['fields']['system_locale']['value']) }} · Moneda {{ strtoupper($sections['system']['fields']['system_currency_code']['value']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-12">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-2">Controles activos</div>
                    <div class="h5 mb-1">{{ (int) $sections['security']['fields']['security_session_idle_minutes']['value'] }} min de inactividad</div>
                    <div class="text-body-secondary small">Confirmaciones destructivas {{ $sections['security']['fields']['security_confirm_destructive_actions']['value'] ? 'encendidas' : 'apagadas' }}.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        @foreach($sections as $section)
            <div class="col-12" id="{{ $section['key'] }}">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                            <div>
                                <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Sección</div>
                                <h2 class="h4 mb-2">{{ $section['label'] }}</h2>
                                <p class="text-body-secondary mb-0">{{ $section['description'] }}</p>
                            </div>
                            <span class="badge rounded-pill text-bg-light border">Aplicación inmediata</span>
                        </div>

                        <form action="{{ route('system-settings.update', $section['key']) }}#{{ $section['key'] }}" method="POST" @if($section['key'] === 'ui') data-preview-palette-form="true" @endif>
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                @foreach($section['fields'] as $field)
                                    <div class="{{ $field['type'] === 'boolean' || (!empty($field['options']) && collect($field['options'])->contains(fn ($option) => !empty($option['colors'] ?? []))) ? 'col-12' : 'col-xl-6' }}">
                                        @if($field['type'] === 'boolean')
                                            <div class="form-check form-switch border rounded-3 px-3 py-3 h-100 bg-light-subtle">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="{{ $field['name'] }}"
                                                    name="{{ $field['name'] }}"
                                                    value="1"
                                                    @checked(filter_var(old($field['name'], $field['value']), FILTER_VALIDATE_BOOLEAN))
                                                >
                                                <label class="form-check-label fw-semibold ms-2" for="{{ $field['name'] }}">{{ $field['label'] }}</label>
                                                <div class="small text-body-secondary mt-2">{{ $field['help'] }}</div>
                                            </div>
                                        @else
                                            <label for="{{ $field['name'] }}" class="form-label">{{ $field['label'] }}</label>

                                            @if($field['type'] === 'select')
                                                @if(!empty($field['options']) && collect($field['options'])->contains(fn ($option) => !empty($option['colors'] ?? [])))
                                                    <div class="settings-palette-panel">
                                                        <div class="settings-palette-panel__header">
                                                            <div>
                                                                <div class="settings-palette-panel__eyebrow">Selección visual</div>
                                                                <div class="settings-palette-panel__title">Elige una paleta operativa</div>
                                                            </div>
                                                            <div class="settings-palette-panel__meta" data-palette-preview-status>Vista previa en vivo. Solo se guarda cuando envías esta sección.</div>
                                                        </div>

                                                    <div class="settings-palette-grid" role="radiogroup" aria-labelledby="{{ $field['name'] }}-label">
                                                        @foreach($field['options'] as $option)
                                                            @php($isSelected = (string) old($field['name'], $field['value']) === (string) $option['value'])
                                                            <label
                                                                class="settings-palette-card {{ $isSelected ? 'settings-palette-card--active' : '' }}"
                                                                for="{{ $field['name'] }}_{{ $loop->index }}"
                                                                style="--palette-color-1: {{ $option['colors'][0] ?? '#ffffff' }}; --palette-color-2: {{ $option['colors'][1] ?? '#1f2731' }}; --palette-color-3: {{ $option['colors'][2] ?? '#dc7446' }}; --palette-color-4: {{ $option['colors'][3] ?? '#1f2731' }};"
                                                            >
                                                                <input
                                                                    class="settings-palette-input"
                                                                    type="radio"
                                                                    id="{{ $field['name'] }}_{{ $loop->index }}"
                                                                    name="{{ $field['name'] }}"
                                                                    value="{{ $option['value'] }}"
                                                                    data-palette-label="{{ $option['label'] }}"
                                                                    @checked($isSelected)
                                                                >
                                                                <div class="settings-palette-preview" aria-hidden="true">
                                                                    <div class="settings-palette-preview__topbar"></div>
                                                                    <div class="settings-palette-preview__body">
                                                                        <div class="settings-palette-preview__nav"></div>
                                                                        <div class="settings-palette-preview__content">
                                                                            <span class="settings-palette-preview__badge"></span>
                                                                            <span class="settings-palette-preview__line settings-palette-preview__line--strong"></span>
                                                                            <span class="settings-palette-preview__line"></span>
                                                                            <span class="settings-palette-preview__line settings-palette-preview__line--short"></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="settings-palette-swatches">
                                                                    @foreach($option['colors'] ?? [] as $color)
                                                                        <span class="settings-palette-swatch" style="background-color: {{ $color }};"></span>
                                                                    @endforeach
                                                                </div>
                                                                <div class="settings-palette-title">{{ $option['label'] }}</div>
                                                                @if(!empty($option['description']))
                                                                    <div class="settings-palette-description">{{ $option['description'] }}</div>
                                                                @endif
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    </div>
                                                @else
                                                    <select
                                                        id="{{ $field['name'] }}"
                                                        name="{{ $field['name'] }}"
                                                        class="form-select"
                                                        @if($section['key'] === 'ui' && $field['name'] === 'ui_density') data-preview-density-select="true" @endif
                                                    >
                                                        @foreach($field['options'] as $option)
                                                            <option value="{{ $option['value'] }}" @selected((string) old($field['name'], $field['value']) === (string) $option['value'])>
                                                                {{ $option['label'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @endif

                                                @if(!empty($field['options']) && collect($field['options'])->contains(fn ($option) => !empty($option['colors'] ?? [])))
                                                    <div id="{{ $field['name'] }}-label" class="visually-hidden">{{ $field['label'] }}</div>
                                                @endif
                                            @else
                                                <input
                                                    id="{{ $field['name'] }}"
                                                    name="{{ $field['name'] }}"
                                                    type="{{ $field['type'] === 'number' ? 'number' : 'text' }}"
                                                    value="{{ old($field['name'], $field['value']) }}"
                                                    class="form-control"
                                                    @foreach($field['input'] ?? [] as $attribute => $attributeValue)
                                                        {{ $attribute }}="{{ $attributeValue }}"
                                                    @endforeach
                                                >
                                            @endif

                                            <div class="form-text">{{ $field['help'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                @if($section['key'] === 'ui')
                                    <button type="reset" class="btn btn-outline-dark me-2">Restaurar visualización actual</button>
                                @endif
                                <button type="submit" class="btn btn-primary">{{ $section['key'] === 'ui' ? 'Guardar configuración' : 'Guardar ' . mb_strtolower($section['label']) }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection