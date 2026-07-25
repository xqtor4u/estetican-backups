@php
    $item = $item ?? null;
@endphp
<div class="row g-3">
    <div class="col-12 d-flex flex-column align-items-center mb-3 pb-3 border-bottom">
        <label class="form-label text-muted fw-bold mb-3 text-uppercase" style="letter-spacing: 1px;">Foto del artículo</label>
        <x-image-upload name="photo" :value="$item->photo_path ?? null" previewShape="square" :aspectRatio="1" maxWidth="200px" label="Capturar foto" defaultIcon="bi-box-seam" />

        @if(!empty($item?->photo_path))
            <div class="form-check mt-3">
                <input type="hidden" name="remove_photo" value="0">
                <input class="form-check-input text-danger bg-danger-subtle border-danger shadow-sm" type="checkbox" value="1" id="remove_photo" name="remove_photo">
                <label class="form-check-label small fw-bold text-danger" for="remove_photo">Quitar foto sin sustituir</label>
            </div>
        @endif
        <div class="form-text text-center mt-2 w-75">Se usa en el listado de artículos y al armar Grupos — ayuda a identificar el producto de un vistazo.</div>
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">Nombre</label>
        <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $item->name ?? '') }}" required>
    </div>

    @php
        $departmentOptions = ['Farmacia', 'Vacunas', 'Accesorios', 'Grooming', 'Hospedaje'];
        $currentDepartment = old('department', $item->department ?? '');
        if ($currentDepartment !== '' && ! in_array($currentDepartment, $departmentOptions, true)) {
            $departmentOptions[] = $currentDepartment;
        }
    @endphp
    <div class="col-md-6">
        <label for="department" class="form-label">Departamento</label>
        <select id="department" name="department" class="form-select">
            <option value="" @selected($currentDepartment === '')>Sin departamento</option>
            @foreach($departmentOptions as $departmentOption)
                <option value="{{ $departmentOption }}" @selected($currentDepartment === $departmentOption)>{{ $departmentOption }}</option>
            @endforeach
        </select>
        <div class="form-text">Determina en qué selectores clínicos aparece este artículo — "Vacunas" en el módulo de Vacunas, "Farmacia" en Recetas.</div>
    </div>

    <div class="col-md-6">
        <label for="meta_category" class="form-label">Categoría (Meta / Google)</label>
        <input id="meta_category" type="text" name="meta_category" class="form-control" list="meta-category-suggestions" value="{{ old('meta_category', $item->meta_category ?? '') }}" placeholder="Ej. Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Pet ID Tags">
        <datalist id="meta-category-suggestions">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Pet ID Tags">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Pet Collars &amp; Harnesses">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Pet Leashes">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Pet Grooming Supplies">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Pet Grooming Supplies &gt; Pet Shampoo &amp; Conditioner">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Pet Grooming Supplies &gt; Pet Hair Clippers &amp; Trimmers">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Pet Grooming Supplies &gt; Pet Combs &amp; Brushes">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Pet Grooming Supplies &gt; Pet Nail Tools">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Dog Supplies &gt; Dog Food">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Dog Supplies &gt; Dog Apparel">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Dog Supplies &gt; Dog Beds">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Dog Supplies &gt; Dog Toys">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Dog Supplies &gt; Dog Treats">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Cat Supplies &gt; Cat Food">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Cat Supplies &gt; Cat Litter">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Cat Supplies &gt; Cat Toys">
            <option value="Animals &amp; Pet Supplies &gt; Pet Supplies &gt; Cat Supplies &gt; Cat Treats">
        </datalist>
        <div class="form-text">Opcional — categoría de Google/Meta para el catálogo de WhatsApp. <strong>Formato aún no verificado contra la API real de Meta.</strong></div>
    </div>

    <div class="col-md-6">
        <label for="brand" class="form-label">Marca</label>
        <input id="brand" type="text" name="brand" class="form-control" value="{{ old('brand', $item->brand ?? '') }}">
    </div>

    <div class="col-md-6">
        <label for="presentation" class="form-label">Presentación</label>
        <input id="presentation" type="text" name="presentation" class="form-control" placeholder="Ej. Frasco 1 dosis, Multidosis 10ml" value="{{ old('presentation', $item->presentation ?? '') }}">
    </div>

    <div class="col-md-6">
        <label for="meta_variant_group" class="form-label">Grupo de variante (Meta)</label>
        <input id="meta_variant_group" type="text" name="meta_variant_group" class="form-control" list="meta-variant-group-suggestions" value="{{ old('meta_variant_group', $item->meta_variant_group ?? '') }}" placeholder="Ej. id-tag-38mm">
        <datalist id="meta-variant-group-suggestions">
            @foreach($existingVariantGroups as $group)
                <option value="{{ $group }}">
            @endforeach
        </datalist>
        <div class="form-text">Opcional — usa el <strong>mismo texto exacto</strong> en cada color de un mismo producto para que Meta los agrupe como variantes.</div>
    </div>

    <div class="col-md-6">
        <label for="meta_color" class="form-label">Color</label>
        <input id="meta_color" type="text" name="meta_color" class="form-control" value="{{ old('meta_color', $item->meta_color ?? '') }}" placeholder="Ej. Rojo">
        <div class="form-text">Solo necesario si este artículo forma parte de un grupo de variante por color.</div>
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notas</label>
        <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $item->notes ?? '') }}</textarea>
    </div>

    <div class="col-md-4"
         x-data="{
            margin: {{ (float) $profitMargin }},
            cost: {{ old('cost_price', $item->cost_price ?? '') !== '' ? (float) old('cost_price', $item->cost_price ?? 0) : 'null' }},
            sugerido() {
                if (!this.cost || this.cost <= 0) return '0.00';
                return (this.cost * (1 + this.margin / 100)).toFixed(2);
            }
         }">
        <label for="cost_price" class="form-label">Costo de compra</label>
        <input id="cost_price" type="number" name="cost_price" class="form-control" min="0" step="0.01" placeholder="Opcional"
               value="{{ old('cost_price', isset($item) && $item->cost_price !== null ? number_format((float) $item->cost_price, 2, '.', '') : '') }}"
               x-model.number="cost">
        <div class="form-text">
            Con margen del {{ rtrim(rtrim(number_format($profitMargin, 2), '0'), '.') }}% (<a href="{{ route('system-settings.index') }}#store" target="_blank">cambiar</a>),
            precio sugerido: $<span x-text="sugerido()">0.00</span>
            <button type="button" class="btn btn-sm btn-link p-0 align-baseline" @click="document.getElementById('price').value = sugerido()">usar sugerido</button>
        </div>
    </div>

    <div class="col-md-4">
        <label for="price" class="form-label">Precio de venta</label>
        <input id="price" type="number" name="price" class="form-control" min="0" step="0.01" placeholder="Opcional" value="{{ old('price', isset($item) && $item->price !== null ? number_format((float) $item->price, 2, '.', '') : '') }}">
        <div class="form-text">Si se deja vacío, el asistente IA dirá "precio a consultar".</div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Existencias</label>
        <input type="text" class="form-control" value="{{ $item->stock_quantity ?? 0 }}" disabled>
        <div class="form-text">
            @if(isset($item))
                Se ajusta registrando movimientos (ver abajo), no editando este número. Con 0, el asistente IA nunca lo mencionará.
            @else
                Arranca en 0 — se registran movimientos después de crear el artículo.
            @endif
        </div>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $item->is_active ?? true))>
            <label class="form-check-label" for="is_active">Artículo activo</label>
        </div>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="ai_visible" value="0">
            <input id="ai_visible" class="form-check-input" type="checkbox" name="ai_visible" value="1" @checked((bool) old('ai_visible', $item->ai_visible ?? false))>
            <label class="form-check-label" for="ai_visible">Visible para el asistente IA</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-text">Solo se muestra al chatbot público si está activo, marcado como visible y con existencias &gt; 0.</div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('items.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</div>
