@php
    $screenDebugId = 'SysSetInd';
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
        @foreach($sections as $section)
            @if(in_array($section['key'], ['ui', 'branding', 'guarantees', 'clinical', 'fiscal', 'email_service']))
                <a href="#{{ $section['key'] }}" class="btn btn-sm btn-outline-dark">
                    @if($section['key'] === 'email_service') Correo 
                    @elseif($section['key'] === 'clinical') Clínica
                    @elseif($section['key'] === 'guarantees') Garantías
                    @else {{ $section['label'] }} @endif
                </a>
            @endif
        @endforeach
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
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-2">Identidad Corporativa</div>
                    <div class="h5 mb-1">{{ $sections['branding']['fields']['brand_business_name']['value'] ?? 'EstetiCAN' }}</div>
                    <div class="text-body-secondary small">Logo Web: {{ $sections['branding']['fields']['brand_logo_web']['value'] ? 'Configurado' : 'Predeterminado' }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-12">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-2">Servicio de Correo</div>
                    <div class="h5 mb-1">{{ $sections['email_service']['fields']['mail_host']['value'] ?? 'No configurado' }}</div>
                    <div class="text-body-secondary small">Envío automático: {{ ($sections['clinical']['fields']['operational_auto_email_report']['value'] ?? false) ? 'Activo' : 'Inactivo' }}</div>
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

                        <form action="{{ route('system-settings.update', $section['key']) }}#{{ $section['key'] }}" method="POST" enctype="multipart/form-data" @if($section['key'] === 'ui') data-preview-palette-form="true" @endif>
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                @foreach($section['fields'] as $field)
                                    <div class="{{ $field['type'] === 'boolean' || $field['type'] === 'image' || (!empty($field['options']) && collect($field['options'])->contains(fn ($option) => !empty($option['colors'] ?? []))) ? 'col-12' : 'col-xl-6' }}">
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
                                                <div class="small text-body-secondary mt-2">{{ $field['help'] ?? '' }}</div>
                                            </div>
                                        @elseif($field['type'] === 'image')
                                            @php($isFavicon = $field['name'] === 'brand_favicon')
                                            <div class="border rounded-3 p-3 bg-light-subtle">
                                                <label class="form-label fw-semibold d-block">{{ $field['label'] }}</label>
                                                <x-image-upload
                                                    :name="$field['name']"
                                                    :value="$field['value']"
                                                    :label="null"
                                                    previewShape="rect"
                                                    :aspectRatio="$isFavicon ? 1 : 'free'"
                                                    previewFit="contain"
                                                    defaultIcon="bi-image"
                                                    :maxWidth="$isFavicon ? '120px' : '260px'"
                                                    outputFormat="image/png"
                                                    :maxOutputWidth="$field['image_max_width'] ?? 640"
                                                    :maxOutputHeight="$field['image_max_height'] ?? 240"
                                                    class="mt-2"
                                                />
                                                <div class="form-text mt-1 small">{{ $field['help'] ?? '' }}</div>
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
                                            @else
                                                <input
                                                    id="{{ $field['name'] }}"
                                                    name="{{ $field['name'] }}"
                                                    type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'password' ? 'password' : 'text') }}"
                                                    value="{{ $field['type'] === 'password' ? '' : old($field['name'], $field['value']) }}"
                                                    class="form-control"
                                                    @foreach($field['input'] ?? [] as $attribute => $attributeValue)
                                                        {{ $attribute }}="{{ $attributeValue }}"
                                                    @endforeach
                                                    @if($field['type'] === 'password' && $field['value']) placeholder="******** (Ingresa nueva para cambiar)" @endif
                                                >
                                            @endif

                                            <div class="form-text">{{ $field['help'] ?? '' }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div>
                                    @if($section['key'] === 'email_service')
                                        <button type="button" class="btn btn-outline-primary" data-test-smtp="true">
                                            <i class="bi bi-envelope-check me-1"></i> Probar Conexión
                                        </button>
                                    @endif
                                </div>
                                <div>
                                    @if($section['key'] === 'ui')
                                        <button type="reset" class="btn btn-outline-dark me-2">Restaurar visualización actual</button>
                                    @endif
                                    <button type="submit" class="btn btn-primary">{{ $section['key'] === 'ui' ? 'Guardar configuración' : 'Guardar ' . mb_strtolower($section['label']) }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script nonce="{{ csp_nonce() }}">
    document.addEventListener('DOMContentLoaded', function() {
        const testBtn = document.querySelector('[data-test-smtp="true"]');
        if (testBtn) {
            testBtn.addEventListener('click', function() {
                const form = testBtn.closest('form');

                // La ruta de prueba es POST real, sin spoofing — pero este form trae
                // @method('PUT') (hidden _method=PUT) para el submit normal a
                // system-settings.update. Si no se quita antes de enviar, Laravel
                // interpreta el POST como PUT y lo enruta a update('smtp-test'),
                // que no es una sección válida → 404 (ver NT-030).
                const methodField = form.querySelector('input[name="_method"]');
                if (methodField) {
                    methodField.disabled = true;
                }

                form.action = "{{ route('system-settings.smtp-test') }}";
                form.submit();

                if (methodField) {
                    setTimeout(() => { methodField.disabled = false; }, 100);
                }
            });
        }
    });
</script>
@endpush
@endsection