@php
    $service = $service ?? $copySource ?? null;
    $isCopy = isset($copySource) && !isset($service->id);
    $serviceTypeOptions = ['spa' => 'Spa', 'hotel' => 'Hotel', 'extra' => 'Extra', 'combo' => 'Combo'];
    if($isCopy) {
        $service->code = $service->code . '-COPIA';
        $service->name = $service->name . ' (copia)';
    }
@endphp

<div class="row g-3">
    <div class="col-md-3">
        <label for="code" class="form-label">Código</label>
        <input id="code" type="text" name="code" class="form-control" value="{{ old('code', $service->code ?? '') }}" placeholder="Autogenerado si se deja vacío">
    </div>

    <div class="col-md-4">
        <label for="type" class="form-label">Tipo</label>
        <select id="type" name="type" class="form-select" required>
            <option value="">Seleccionar</option>
            @foreach($serviceTypeOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $service->type ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-5">
        <label for="name" class="form-label">Nombre del servicio</label>
        <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $service->name ?? '') }}" required>
    </div>

    <div class="col-md-8">
        <label for="operator_role_id" class="form-label">Tipo de operador</label>
        <select id="operator_role_id" name="operator_role_id" class="form-select" required>
            <option value="">Seleccionar</option>
            @foreach($operatorRoles as $operatorRole)
                <option value="{{ $operatorRole->id }}" @selected((string) old('operator_role_id', $service->operator_role_id ?? '') === (string) $operatorRole->id)>
                    {{ $operatorRole->name }}@if($operatorRole->code) ({{ $operatorRole->code }})@endif
                </option>
            @endforeach
        </select>
        <div class="form-text">Cada servicio queda ligado a la especialidad operativa que debe poder ejecutarlo.</div>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <a href="{{ route('operator-roles.create', ['return_to' => request()->fullUrl()]) }}" class="btn btn-outline-secondary w-100">Nuevo tipo de operador</a>
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Descripción base</label>
        <textarea id="description" name="description" class="form-control" rows="4" placeholder="Explica alcance, variaciones permitidas y criterio operativo.">{{ old('description', $service->description ?? '') }}</textarea>
    </div>

    <div class="col-md-4">
        <label for="suggested_price" class="form-label">Precio sugerido</label>
        <input id="suggested_price" type="number" name="suggested_price" class="form-control" min="0" step="0.01" value="{{ old('suggested_price', isset($service) ? number_format((float) $service->suggested_price, 2, '.', '') : '') }}" required>
    </div>

    <div class="col-md-4">
        <label for="suggested_duration_minutes" class="form-label">Duración sugerida (min)</label>
        <input id="suggested_duration_minutes" type="number" name="suggested_duration_minutes" class="form-control" min="0" step="1" value="{{ old('suggested_duration_minutes', $service->suggested_duration_minutes ?? '') }}" required>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $service->is_active ?? true))>
            <label class="form-check-label" for="is_active">Servicio activo</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('services.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>