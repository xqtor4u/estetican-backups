<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label fw-semibold">Nombre de la caja <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $cashRegister?->name) }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Ej. Caja principal" maxlength="255" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Sucursal <span class="text-danger">*</span></label>
        <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
            <option value="">— Selecciona —</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id', $cashRegister?->branch_id) == $branch->id)>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch border rounded-3 px-3 py-3 bg-light-subtle">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                {{ old('is_active', $cashRegister?->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label ms-2" for="is_active">
                <span class="fw-semibold">Caja activa</span><br>
                <small class="text-body-secondary">Solo las cajas activas pueden tener sesiones de apertura y corte.</small>
            </label>
        </div>
    </div>
</div>
