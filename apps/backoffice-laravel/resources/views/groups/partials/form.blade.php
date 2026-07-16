@php
    $group = $group ?? null;
@endphp
<div class="row g-3">
    <div class="col-md-8">
        <label for="name" class="form-label">Nombre</label>
        <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $group->name ?? '') }}" required>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $group->is_active ?? true))>
            <label class="form-check-label" for="is_active">Grupo activo</label>
        </div>
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Descripción</label>
        <textarea id="description" name="description" class="form-control" rows="2" placeholder="Ej. Corte de cola de perro: incluye mano de obra veterinaria y curación.">{{ old('description', $group->description ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notas</label>
        <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $group->notes ?? '') }}</textarea>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('groups.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>
