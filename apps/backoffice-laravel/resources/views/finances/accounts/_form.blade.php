@php
    $typeOptions = [
        'activo'  => 'Activo',
        'pasivo'  => 'Pasivo',
        'capital' => 'Capital',
        'ingreso' => 'Ingreso',
        'gasto'   => 'Gasto',
    ];
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
        <input type="text" name="code" value="{{ old('code', $account?->code) }}"
            class="form-control @error('code') is-invalid @enderror"
            placeholder="Ej. 4100" maxlength="20" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Código único. Ej: 1000, 4100, 5200.</div>
    </div>

    <div class="col-md-8">
        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $account?->name) }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Ej. Ingresos por Grooming" maxlength="255" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
            <option value="">— Selecciona —</option>
            @foreach($typeOptions as $val => $label)
                <option value="{{ $val }}" @selected(old('type', $account?->type) === $val)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Cuenta padre</label>
        <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
            <option value="">— Sin padre (cuenta raíz) —</option>
            @foreach($parents as $parent)
                <option value="{{ $parent->id }}" @selected(old('parent_id', $account?->parent_id) == $parent->id)>
                    {{ $parent->code }} — {{ $parent->name }}
                </option>
            @endforeach
        </select>
        @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Solo se muestran cuentas agrupadora.</div>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Descripción</label>
        <textarea name="description" rows="2"
            class="form-control @error('description') is-invalid @enderror"
            placeholder="Opcional — propósito contable de esta cuenta">{{ old('description', $account?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <div class="form-check form-switch border rounded-3 px-3 py-3 bg-light-subtle">
            <input class="form-check-input" type="checkbox" name="allows_entries" value="1" id="allows_entries"
                {{ old('allows_entries', $account?->allows_entries ?? true) ? 'checked' : '' }}>
            <label class="form-check-label ms-2" for="allows_entries">
                <span class="fw-semibold">Admite movimientos directos</span><br>
                <small class="text-body-secondary">Desactiva si es una cuenta agrupadora.</small>
            </label>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-check form-switch border rounded-3 px-3 py-3 bg-light-subtle">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                {{ old('is_active', $account?->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label ms-2" for="is_active">
                <span class="fw-semibold">Cuenta activa</span><br>
                <small class="text-body-secondary">Las cuentas inactivas no aparecen en selectores.</small>
            </label>
        </div>
    </div>
</div>
