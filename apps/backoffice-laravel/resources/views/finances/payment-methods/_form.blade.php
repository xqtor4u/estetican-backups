@php
    $typeOptions = [
        'cash'     => 'Efectivo',
        'card'     => 'Tarjeta',
        'transfer' => 'Transferencia',
        'crypto'   => 'Criptomoneda',
        'gateway'  => 'Pasarela de pago',
    ];
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
        <input type="text" name="code" value="{{ old('code', $paymentMethod?->code) }}"
            class="form-control @error('code') is-invalid @enderror"
            placeholder="Ej. EFECT" maxlength="30" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Identificador único en mayúsculas.</div>
    </div>

    <div class="col-md-8">
        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $paymentMethod?->name) }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Ej. Efectivo" maxlength="255" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
            <option value="">— Selecciona —</option>
            @foreach($typeOptions as $val => $label)
                <option value="{{ $val }}" @selected(old('type', $paymentMethod?->type) === $val)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Cuenta contable asociada</label>
        <select name="account_id" class="form-select @error('account_id') is-invalid @enderror">
            <option value="">— Sin cuenta —</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected(old('account_id', $paymentMethod?->account_id) == $account->id)>
                    {{ $account->code }} — {{ $account->name }}
                </option>
            @endforeach
        </select>
        @error('account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">La cuenta que se abona al registrar un cobro con este método.</div>
    </div>

    <div class="col-md-6">
        <div class="form-check form-switch border rounded-3 px-3 py-3 bg-light-subtle">
            <input class="form-check-input" type="checkbox" name="requires_reference" value="1" id="requires_reference"
                {{ old('requires_reference', $paymentMethod?->requires_reference ?? false) ? 'checked' : '' }}>
            <label class="form-check-label ms-2" for="requires_reference">
                <span class="fw-semibold">Requiere referencia</span><br>
                <small class="text-body-secondary">Solicitar folio o número de autorización al cobrar.</small>
            </label>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-check form-switch border rounded-3 px-3 py-3 bg-light-subtle">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                {{ old('is_active', $paymentMethod?->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label ms-2" for="is_active">
                <span class="fw-semibold">Activo</span><br>
                <small class="text-body-secondary">Solo los métodos activos aparecen en la pantalla de cobro.</small>
            </label>
        </div>
    </div>
</div>
