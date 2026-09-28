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

    @php($generalHours = app(\App\Support\SystemSettings\BusinessHours::class))
    <div class="col-md-3">
        <label for="opening_time" class="form-label">Abre</label>
        <input id="opening_time" type="time" name="opening_time" class="form-control @error('opening_time') is-invalid @enderror" value="{{ old('opening_time', $branch->opening_time ?? '') }}">
        @error('opening_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label for="closing_time" class="form-label">Cierra</label>
        <input id="closing_time" type="time" name="closing_time" class="form-control @error('closing_time') is-invalid @enderror" value="{{ old('closing_time', $branch->closing_time ?? '') }}">
        @error('closing_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-text">
            Horario operativo propio de esta sucursal. Déjalo vacío para usar el horario general
            ({{ $generalHours->openingTime() }}–{{ $generalHours->closingTime() }}, en Configuración).
        </div>
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