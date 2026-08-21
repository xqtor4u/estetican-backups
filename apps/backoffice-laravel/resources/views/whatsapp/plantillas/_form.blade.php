<div x-data="{
    context: '{{ old('context', $template->context ?? 'cita') }}',
    variablesByContext: {{ \Illuminate\Support\Js::from($variablesByContext) }},
    body: @js(old('body', $template->body ?? '')),
    showPreview: false,
    insert(variable) {
        const el = this.$refs.bodyInput;
        const start = el.selectionStart;
        const end = el.selectionEnd;
        this.body = this.body.slice(0, start) + variable + this.body.slice(end);
        this.$nextTick(() => {
            el.focus();
            el.selectionStart = el.selectionEnd = start + variable.length;
        });
    },
    get preview() {
        const samples = this.context === 'recurrencia'
            ? { cliente: 'Juan Pérez', mascota: 'Firulais', servicio: 'Baño y corte', ultima_fecha: '01/06/2026', dias_vencido: '15' }
            : this.context === 'cliente'
            ? { cliente: 'Juan Pérez' }
            : this.context === 'general'
            ? { cliente: 'Juan Pérez', mascota: 'Firulais', servicio: '', fecha: '', hora: '', ultima_fecha: '', dias_vencido: '' }
            : { cliente: 'Juan Pérez', mascota: 'Firulais', servicio: 'Baño y corte', fecha: '10/07/2026', hora: '10:30 AM' };
        let result = this.body;
        for (const [key, value] of Object.entries(samples)) {
            result = result.split('{' + key + '}').join(value);
        }
        return result;
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

    <div class="mb-3">
        <label class="form-label fw-semibold">Asunto (para envío por correo)</label>
        <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
            value="{{ old('subject', $template->subject ?? '') }}" placeholder="Ej. Recordatorio de cita — {mascota}">
        @error('subject')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Admite las mismas variables que el mensaje. Se ignora al enviar por WhatsApp.</div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Contexto de uso</label>
        <select name="context" class="form-select @error('context') is-invalid @enderror" x-model="context">
            <option value="cita">Bandeja diaria (citas del día)</option>
            <option value="recurrencia">Recurrencias (servicio periódico vencido)</option>
            <option value="cliente">Mensaje directo a cliente (ficha del cliente)</option>
            <option value="general">General (campaña, oferta de temporada u otro mensaje)</option>
        </select>
        @error('context')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Determina en qué pantalla aparece disponible esta plantilla y qué variables aplican.</div>
    </div>

    <div class="mb-2">
        <label class="form-label fw-semibold">Variables disponibles — clic para insertar en el mensaje</label>
        <div class="d-flex flex-wrap gap-2">
            <template x-for="[key, description] in Object.entries(variablesByContext[context])" :key="key">
                <button type="button" class="btn btn-sm btn-outline-primary" :title="description"
                    @click="insert('{' + key + '}')">
                    <span x-text="'{' + key + '}'"></span>
                </button>
            </template>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Mensaje</label>
        <textarea name="body" x-ref="bodyInput" x-model="body" rows="5" class="form-control @error('body') is-invalid @enderror" required></textarea>
        @error('body')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Las variables se reemplazan automáticamente por los datos de cada cita o recurrencia al enviar.</div>
    </div>

    <button type="button" class="btn btn-sm btn-outline-secondary mb-2" @click="showPreview = !showPreview">
        <span x-show="!showPreview">Previsualizar</span>
        <span x-show="showPreview">Ocultar vista previa</span>
    </button>

    <div x-show="showPreview" class="mb-3">
        <div class="border rounded p-3 bg-light small mb-1" style="white-space: pre-wrap;" x-text="preview"></div>
        <div class="form-text">Vista previa con datos de ejemplo — el mensaje real usará los datos de cada cita o mascota.</div>
    </div>

    <div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
            {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">Plantilla activa</label>
    </div>
</div>
