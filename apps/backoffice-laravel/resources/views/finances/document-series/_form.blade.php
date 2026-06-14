@php
    $typeOptions = ['recibo' => 'Recibo', 'factura' => 'Factura', 'sin_documento' => 'Sin documento'];
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Tipo de documento <span class="text-danger">*</span></label>
        <select name="document_type" class="form-select @error('document_type') is-invalid @enderror" required>
            <option value="">— Selecciona —</option>
            @foreach($typeOptions as $val => $label)
                <option value="{{ $val }}" @selected(old('document_type', $documentSeries?->document_type) === $val)>{{ $label }}</option>
            @endforeach
        </select>
        @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Nombre de la serie <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $documentSeries?->name) }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Ej. Recibos de pago" maxlength="255" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Prefijo <span class="text-danger">*</span></label>
        <input type="text" name="prefix" value="{{ old('prefix', $documentSeries?->prefix) }}"
            class="form-control @error('prefix') is-invalid @enderror"
            placeholder="R-" maxlength="20" required>
        @error('prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Sufijo</label>
        <input type="text" name="suffix" value="{{ old('suffix', $documentSeries?->suffix) }}"
            class="form-control @error('suffix') is-invalid @enderror"
            placeholder="" maxlength="20">
        @error('suffix')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Relleno de ceros <span class="text-danger">*</span></label>
        <input type="number" name="padding" value="{{ old('padding', $documentSeries?->padding ?? 4) }}"
            class="form-control @error('padding') is-invalid @enderror"
            min="1" max="10" required>
        @error('padding')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Ej. 4 → R-0001</div>
    </div>

    @if(!$documentSeries)
    <div class="col-md-3">
        <label class="form-label fw-semibold">Número inicial</label>
        <input type="number" name="next_number" value="{{ old('next_number', 1) }}"
            class="form-control @error('next_number') is-invalid @enderror"
            min="1" required>
        @error('next_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @endif

    <div class="col-md-6">
        <label class="form-label fw-semibold">Sucursal</label>
        <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
            <option value="">— Global (todas las sucursales) —</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id', $documentSeries?->branch_id) == $branch->id)>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Deja vacío para usar la misma serie en todas las sucursales.</div>
    </div>

    <div class="col-md-6">
        <div class="form-check form-switch border rounded-3 px-3 py-3 bg-light-subtle h-100 d-flex align-items-center">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                {{ old('is_active', $documentSeries?->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label ms-2" for="is_active">
                <span class="fw-semibold">Serie activa</span><br>
                <small class="text-body-secondary">Solo las series activas se usan para emitir documentos.</small>
            </label>
        </div>
    </div>
</div>
