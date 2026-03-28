@php($branch = $branch ?? null)

<div class="row g-3">
    <div class="col-md-3">
        <label for="code" class="form-label">Clave</label>
        <input id="code" type="text" name="code" class="form-control" value="{{ old('code', $branch->code ?? '') }}" required>
    </div>

    <div class="col-md-9">
        <label for="name" class="form-label">Nombre</label>
        <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $branch->name ?? '') }}" required>
    </div>

    <div class="col-12">
        @include('shared.address-editor', [
            'address' => $branch,
            'useCard' => false,
            'cityLabel' => 'Ciudad / municipio',
            'wrapperClass' => 'border rounded p-3 bg-body-tertiary',
        ])
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notas</label>
        <textarea id="notes" name="notes" class="form-control" rows="4" placeholder="Cobertura, turno base o contexto operativo de la sucursal.">{{ old('notes', $branch->notes ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $branch->is_active ?? true))>
            <label class="form-check-label" for="is_active">Sucursal activa</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>