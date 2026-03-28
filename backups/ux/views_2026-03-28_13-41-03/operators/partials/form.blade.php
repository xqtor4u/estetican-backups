@php($selectedRoleIds = collect(old('role_ids', isset($operator) ? $operator->roles->pluck('id')->all() : []))->map(fn ($roleId) => (int) $roleId)->all())
@php($selectedBranchId = (int) old('branch_id', request()->query('branch_id', isset($operator) ? optional($operator->primaryBranch())->id : '')))

<div class="row g-3">
    <div class="col-md-3">
        <label for="code" class="form-label">Clave</label>
        <input id="code" type="text" name="code" class="form-control" value="{{ old('code', $operator->code ?? '') }}" required>
    </div>

    <div class="col-md-6">
        <label for="full_name" class="form-label">Nombre completo</label>
        <input id="full_name" type="text" name="full_name" class="form-control" value="{{ old('full_name', $operator->full_name ?? $operator->name ?? '') }}" required>
    </div>

    <div class="col-md-4">
        <label for="role" class="form-label">Rol operativo</label>
        <input id="role" type="text" name="role" class="form-control" value="{{ old('role', $operator->role ?? '') }}" placeholder="Se asigna automáticamente según el tipo principal" readonly>
    </div>

    <div class="col-md-3">
        <label for="ine_number" class="form-label">INE</label>
        <input id="ine_number" type="text" name="ine_number" class="form-control" value="{{ old('ine_number', $operator->ine_number ?? '') }}" placeholder="Folio o referencia INE">
    </div>

    <div class="col-md-3">
        <label for="imss_number" class="form-label">Número de IMSS</label>
        <input id="imss_number" type="text" name="imss_number" class="form-control" value="{{ old('imss_number', $operator->imss_number ?? '') }}">
    </div>

    <div class="col-md-3">
        <label for="phone" class="form-label">Teléfono</label>
        <input id="phone" type="text" name="phone" class="form-control" value="{{ old('phone', $operator->phone ?? '') }}">
    </div>

    <div class="col-md-5">
        <label for="profile_photo" class="form-label">Foto del operador</label>
        <input id="profile_photo" type="file" name="profile_photo" class="form-control" accept="image/*">
        <div class="form-text">Sube una foto frontal o identificable del operador para ficha y listados.</div>
    </div>

    <div class="col-md-4">
        @if(!empty($operator?->profile_photo_thumbnail_url))
            <label class="form-label d-block">Vista actual</label>
            <div class="d-flex align-items-center gap-3 border rounded p-2 bg-body-tertiary-subtle">
                <img src="{{ $operator->profile_photo_thumbnail_url }}" alt="Foto de {{ $operator->full_name ?: $operator->name }}" class="rounded app-avatar-sm app-media-cover">
                <div class="form-check">
                    <input type="hidden" name="remove_profile_photo" value="0">
                    <input class="form-check-input" type="checkbox" value="1" id="remove_profile_photo" name="remove_profile_photo">
                    <label class="form-check-label" for="remove_profile_photo">Quitar foto actual</label>
                </div>
            </div>
        @endif
    </div>

    <div class="col-md-3">
        <label for="hire_date" class="form-label">Fecha de contratación</label>
        <input id="hire_date" type="date" name="hire_date" class="form-control" value="{{ old('hire_date', isset($operator) && $operator->hire_date ? $operator->hire_date->format('Y-m-d') : '') }}">
    </div>

    <div class="col-12">
        <label for="address" class="form-label">Dirección</label>
        <textarea id="address" name="address" class="form-control" rows="2" placeholder="Domicilio del operador">{{ old('address', $operator->address ?? '') }}</textarea>
    </div>

    <div class="col-md-6">
        <label for="emergency_contact_name" class="form-label">Persona de contacto</label>
        <input id="emergency_contact_name" type="text" name="emergency_contact_name" class="form-control" value="{{ old('emergency_contact_name', $operator->emergency_contact_name ?? '') }}" placeholder="Contacto de emergencias">
    </div>

    <div class="col-md-6">
        <label for="emergency_contact_phone" class="form-label">Teléfono de emergencia</label>
        <input id="emergency_contact_phone" type="text" name="emergency_contact_phone" class="form-control" value="{{ old('emergency_contact_phone', $operator->emergency_contact_phone ?? '') }}">
    </div>

    <div class="col-md-8">
        <label class="form-label d-block">Tipos de operador</label>
        <div class="border rounded p-3 bg-body-tertiary-subtle">
            @forelse($availableRoles as $availableRole)
                <div class="form-check mb-2">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="role_ids[]"
                        value="{{ $availableRole->id }}"
                        id="role-{{ $availableRole->id }}"
                        @checked(in_array($availableRole->id, $selectedRoleIds, true))
                    >
                    <label class="form-check-label" for="role-{{ $availableRole->id }}">
                        <span class="fw-semibold">{{ $availableRole->name }}</span>
                        <span class="text-body-secondary small d-block">{{ $availableRole->code }} · {{ $availableRole->description ?: 'Sin descripción' }}</span>
                    </label>
                </div>
            @empty
                <p class="text-body-secondary mb-0">Aún no hay tipos de operador. Crea primero el catálogo para poder asignarlos.</p>
            @endforelse
        </div>
    </div>

    <div class="col-md-8">
        <label for="branch_id" class="form-label">Base de operación</label>
        <select id="branch_id" name="branch_id" class="form-select">
            <option value="">Seleccionar sucursal</option>
            @foreach($availableBranches as $availableBranch)
                <option value="{{ $availableBranch->id }}" @selected($selectedBranchId === $availableBranch->id)>
                    {{ $availableBranch->name }}@if($availableBranch->code) ({{ $availableBranch->code }})@endif
                </option>
            @endforeach
        </select>
        <div class="form-text">La base operativa se selecciona desde el catálogo de sucursales, ya no se captura como texto libre.</div>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <a href="{{ route('branches.create', ['return_to' => request()->fullUrl()]) }}" class="btn btn-outline-secondary w-100">Nueva sucursal</a>
    </div>

    <div class="col-12">
        <div class="small text-body-secondary">
            El primer tipo marcado se tomará como referencia principal para el campo legado de rol y futuras reglas de asignación.
        </div>
    </div>

    <div class="col-md-4">
        <label for="hourly_rate" class="form-label">Pago por hora</label>
        <input
            id="hourly_rate"
            type="number"
            name="hourly_rate"
            class="form-control"
            min="0"
            step="0.01"
            value="{{ old('hourly_rate', isset($operator) && $operator->currentCompensationProfile() ? number_format((float) $operator->currentCompensationProfile()->hourly_rate, 2, '.', '') : '') }}"
            placeholder="Monto por hora"
        >
        <div class="form-text">Si se deja vacío, se podrá tomar después la tarifa base del tipo principal.</div>
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notas</label>
        <textarea id="notes" name="notes" class="form-control" rows="4" placeholder="Disponibilidad, especialidad o contexto operativo.">{{ old('notes', $operator->notes ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $operator->is_active ?? true))>
            <label class="form-check-label" for="is_active">Operador activo</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('operators.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>