<div x-data="{
    insert(variable) {
        const el = this.$refs.bodyInput;
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const text = el.value;
        el.value = text.slice(0, start) + variable + text.slice(end);
        el.focus();
        el.selectionStart = el.selectionEnd = start + variable.length;
    }
}">
    <div class="mb-3">
        <label class="form-label fw-semibold">Nombre de la plantilla</label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $template->name ?? '') }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-2">
        <label class="form-label fw-semibold">Variables disponibles — clic para insertar en el mensaje</label>
        <div class="d-flex flex-wrap gap-2">
            @foreach($variables as $key => $description)
                <button type="button" class="btn btn-sm btn-outline-primary" title="{{ $description }}"
                    @click="insert('{{ '{' . $key . '}' }}')">
                    {{ '{' . $key . '}' }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Mensaje</label>
        <textarea name="body" x-ref="bodyInput" rows="5" class="form-control @error('body') is-invalid @enderror" required>{{ old('body', $template->body ?? '') }}</textarea>
        @error('body')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Las variables se reemplazan automáticamente por los datos de cada cita al enviar.</div>
    </div>

    <div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
            {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">Plantilla activa (disponible en la bandeja diaria)</label>
    </div>
</div>
