<div class="row g-3">
    <div class="col-md-3">
        <label for="code" class="form-label">Clave</label>
        <input id="code" type="text" name="code" class="form-control" value="{{ old('code', $operatorRole->code ?? '') }}" required>
    </div>

    <div class="col-md-5">
        <label for="name" class="form-label">Nombre</label>
        <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $operatorRole->name ?? '') }}" required>
    </div>

    <div class="col-md-4">
        <label for="default_hourly_rate" class="form-label">Costo por hora base</label>
        <input id="default_hourly_rate" type="number" name="default_hourly_rate" class="form-control" min="0" step="0.01" value="{{ old('default_hourly_rate', isset($operatorRole) && $operatorRole->default_hourly_rate !== null ? number_format((float) $operatorRole->default_hourly_rate, 2, '.', '') : '') }}">
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Descripción</label>
        <textarea id="description" name="description" class="form-control" rows="4" placeholder="Define con claridad esta especialidad operativa y su alcance.">{{ old('description', $operatorRole->description ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $operatorRole->is_active ?? true))>
            <label class="form-check-label" for="is_active">Tipo activo</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('operator-roles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>