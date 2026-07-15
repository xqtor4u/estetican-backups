@php
    $item = $item ?? null;
@endphp
<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Nombre</label>
        <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $item->name ?? '') }}" required>
    </div>

    <div class="col-md-6">
        <label for="department" class="form-label">Departamento</label>
        <input id="department" type="text" name="department" class="form-control" list="department-suggestions" value="{{ old('department', $item->department ?? '') }}" placeholder="Ej. Farmacia, Accesorios...">
        <datalist id="department-suggestions">
            <option value="Farmacia">
            <option value="Accesorios">
            <option value="Grooming">
            <option value="Hospedaje">
        </datalist>
        <div class="form-text">Opcional — sugerencia para agrupar en el futuro módulo de inventario.</div>
    </div>

    <div class="col-md-6">
        <label for="brand" class="form-label">Marca</label>
        <input id="brand" type="text" name="brand" class="form-control" value="{{ old('brand', $item->brand ?? '') }}">
    </div>

    <div class="col-md-6">
        <label for="presentation" class="form-label">Presentación</label>
        <input id="presentation" type="text" name="presentation" class="form-control" placeholder="Ej. Frasco 1 dosis, Multidosis 10ml" value="{{ old('presentation', $item->presentation ?? '') }}">
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notas</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $item->notes ?? '') }}</textarea>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $item->is_active ?? true))>
            <label class="form-check-label" for="is_active">Artículo activo</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('items.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>
