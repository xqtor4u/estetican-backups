@php
    $address = $address ?? null;
    $namePrefix = $namePrefix ?? '';
    $oldPrefix = $oldPrefix ?? null;
    $title = $title ?? null;
    $subtitle = $subtitle ?? null;
    $showType = $showType ?? false;
    $allowDelete = $allowDelete ?? false;
    $deleteName = $deleteName ?? null;
    $deleteId = $deleteId ?? null;
    $wrapperClass = $wrapperClass ?? '';
    $wrapperAttributes = $wrapperAttributes ?? [];
    $useCard = $useCard ?? false;
    $cityLabel = $cityLabel ?? 'Ciudad';
    $cityDefault = $cityDefault ?? (string) config('backoffice.system.default_address_city', '');
    $stateDefault = $stateDefault ?? (string) config('backoffice.system.default_address_state', '');
    $countryDefault = $countryDefault ?? (string) config('backoffice.system.default_address_country', 'México');

    $inputName = static fn (string $field) => $namePrefix !== '' ? $namePrefix.'['.$field.']' : $field;
    $oldKey = static fn (string $field) => $oldPrefix !== null && $oldPrefix !== '' ? $oldPrefix.'.'.$field : $field;
    $fieldValue = static fn (string $field, $default = '') => old($oldKey($field), data_get($address, $field, $default));
    $wrapperAttributeString = collect($wrapperAttributes)
        ->map(static fn ($value, $key) => $key.'="'.e($value).'"')
        ->implode(' ');
@endphp

<div class="address-editor{{ $useCard ? ' card' : '' }}{{ $wrapperClass !== '' ? ' '.$wrapperClass : '' }}" data-address-editor {!! $wrapperAttributeString !!}>
    @if($useCard)
        <div class="card-body py-3 px-3">
    @endif

    @if($title || $allowDelete)
        <div class="d-flex justify-content-between align-items-start mb-2 gap-2 flex-wrap">
            <div>
                @if($title)
                    <h5 class="{{ $useCard ? 'card-title' : 'h6' }} mb-1">{{ $title }}</h5>
                @endif
                @if($subtitle)
                    <p class="text-body-secondary small mb-0">{{ $subtitle }}</p>
                @endif
            </div>

            @if($allowDelete && $deleteName && $deleteId)
                <div class="form-check mt-1">
                    <input type="checkbox" class="form-check-input" name="{{ $deleteName }}" value="1" id="{{ $deleteId }}">
                    <label class="form-check-label small" for="{{ $deleteId }}">Eliminar dirección</label>
                </div>
            @endif
        </div>
    @endif

    <div class="row g-2 align-items-end">
        @if($showType)
            <div class="col-md-3">
                <label class="form-label small mb-1">Tipo</label>
                <select name="{{ $inputName('type') }}" class="form-control form-control-sm address-editor-type" required>
                    <option value="home" @selected($fieldValue('type', 'home') === 'home')>Casa</option>
                    <option value="work" @selected($fieldValue('type') === 'work')>Trabajo</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-1">Calle</label>
                <input type="text" name="{{ $inputName('street') }}" value="{{ $fieldValue('street') }}" class="form-control form-control-sm address-editor-street" required>
            </div>
            <div class="col-md-2">
        @else
            <div class="col-md-7">
                <label class="form-label small mb-1">Calle</label>
                <input type="text" name="{{ $inputName('street') }}" value="{{ $fieldValue('street') }}" class="form-control form-control-sm address-editor-street">
            </div>
            <div class="col-md-3">
        @endif
                <label class="form-label small mb-1">Número exterior</label>
                <input type="text" name="{{ $inputName('exterior_number') }}" value="{{ $fieldValue('exterior_number') }}" class="form-control form-control-sm address-editor-exterior">
            </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Interior</label>
            <input type="text" name="{{ $inputName('interior_number') }}" value="{{ $fieldValue('interior_number') }}" class="form-control form-control-sm address-editor-interior">
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-1">Colonia</label>
            <input type="text" name="{{ $inputName('colonia') }}" value="{{ $fieldValue('colonia') }}" class="form-control form-control-sm address-editor-colonia">
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-1">{{ $cityLabel }}</label>
            <input type="text" name="{{ $inputName('city') }}" value="{{ $fieldValue('city', $cityDefault) }}" class="form-control form-control-sm address-editor-city" required>
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-1">Estado</label>
            <input type="text" name="{{ $inputName('state') }}" value="{{ $fieldValue('state', $stateDefault) }}" class="form-control form-control-sm address-editor-state">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Código postal</label>
            <input type="text" name="{{ $inputName('zip') }}" value="{{ $fieldValue('zip') }}" class="form-control form-control-sm address-editor-zip">
        </div>
        <div class="col-md-5">
            <label class="form-label small mb-1">País</label>
            <input type="text" name="{{ $inputName('country') }}" value="{{ $fieldValue('country', $countryDefault) }}" class="form-control form-control-sm address-editor-country" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Latitud</label>
            <input type="number" step="0.00000001" min="-90" max="90" name="{{ $inputName('lat') }}" value="{{ $fieldValue('lat') }}" class="form-control form-control-sm address-editor-lat">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Longitud</label>
            <input type="number" step="0.00000001" min="-180" max="180" name="{{ $inputName('lng') }}" value="{{ $fieldValue('lng') }}" class="form-control form-control-sm address-editor-lng">
        </div>
        <div class="col-12">
            <div class="form-text small">Usa calle, número exterior e interior por separado. Si la dirección cae aproximada, importa el punto exacto desde Maps.</div>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-dark address-editor-geocode-btn">Intentar traer coordenadas</button>
            <a href="#" class="btn btn-sm btn-outline-secondary disabled address-editor-maps-btn" target="_blank" rel="noopener noreferrer" aria-disabled="true">Mostrar en Google Maps</a>
            <span class="small text-body-secondary address-editor-status">Captura al menos calle y ciudad para buscar la dirección con precisión.</span>
        </div>
        <div class="col-md-9">
            <input type="text" class="form-control form-control-sm address-editor-maps-source" placeholder="Copia y pega aquí las coordenadas, por ejemplo 25.6866142,-100.3161126">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-primary w-100 address-editor-import-btn">Importar punto exacto</button>
        </div>
    </div>

    @if($useCard)
        </div>
    @endif
</div>