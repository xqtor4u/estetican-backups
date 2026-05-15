@php
    $resource = $resource ?? $copySource ?? null;
    $isCopy = isset($copySource) && !isset($resource->id);
    if($isCopy) {
        $resource->code = $resource->code . '-COPIA';
        $resource->name = $resource->name . ' (copia)';
    }
    $resourceTypeOptions = [
        'cage' => 'Jaula',
        'room' => 'Espacio',
        'equipment' => 'Equipo',
        'other' => 'Otro',
    ];
    $administrativeStatusOptions = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'retired' => 'Retirado',
    ];
    $operationalStatusOptions = [
        'available' => 'Disponible',
        'occupied' => 'Ocupado',
        'cleaning' => 'Limpieza',
        'maintenance' => 'Mantenimiento',
        'blocked' => 'Bloqueado',
    ];
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="branch_id" class="form-label">Sucursal</label>
        <select id="branch_id" name="branch_id" class="form-select" required>
            <option value="">Seleccionar</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $resource->branch_id ?? request('branch_id')) === (string) $branch->id)>
                    {{ $branch->name }} ({{ $branch->code }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label for="resource_type" class="form-label">Tipo de recurso</label>
        <select id="resource_type" name="resource_type" class="form-select" required>
            @foreach($resourceTypeOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('resource_type', $resource->resource_type ?? 'cage') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <a href="{{ route('branches.create', ['return_to' => request()->fullUrl()]) }}" class="btn btn-outline-secondary w-100">Nueva sucursal</a>
    </div>

    <div class="col-md-3">
        <label for="code" class="form-label">Núm. Serie / Clave</label>
        <input id="code" type="text" name="code" class="form-control" value="{{ old('code', $resource->code ?? '') }}" required>
    </div>

    <div class="col-md-5">
        <label for="name" class="form-label">Nombre</label>
        <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $resource->name ?? '') }}" required>
    </div>

    <div class="col-md-4">
        <label for="capacity_label" class="form-label">Capacidad / tamaño</label>
        <input id="capacity_label" type="text" name="capacity_label" class="form-control" value="{{ old('capacity_label', $resource->capacity_label ?? '') }}" placeholder="Pequeña, mediana, XL, etc.">
    </div>

    <div class="col-md-6">
        <label for="administrative_status" class="form-label">Estado administrativo</label>
        <select id="administrative_status" name="administrative_status" class="form-select" required>
            @foreach($administrativeStatusOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('administrative_status', $resource->administrative_status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label for="operational_status" class="form-label">Estado operativo</label>
        <select id="operational_status" name="operational_status" class="form-select" required>
            @foreach($operationalStatusOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('operational_status', $resource->operational_status ?? 'available') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notas</label>
        <textarea id="notes" name="notes" class="form-control" rows="4" placeholder="Contexto operativo, restricciones o mantenimiento recomendado.">{{ old('notes', $resource->notes ?? '') }}</textarea>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('resources.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>