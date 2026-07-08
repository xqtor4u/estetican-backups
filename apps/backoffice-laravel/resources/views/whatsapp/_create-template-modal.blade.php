{{-- Modal para crear una plantilla nueva sin salir de la bandeja/recurrencias --}}
<div class="modal fade" id="modalCreateTemplate" x-ref="createTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Crear plantilla</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-danger small mb-3" x-show="createTemplateError" x-text="createTemplateError"></div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre de la plantilla</label>
                    <input type="text" class="form-control" x-model="newTemplateName">
                </div>

                <div class="mb-2">
                    <label class="form-label fw-semibold">Variables disponibles — clic para insertar en el mensaje</label>
                    <div class="d-flex flex-wrap gap-2">
                        <template x-for="[key, description] in Object.entries(templateVariables)" :key="key">
                            <button type="button" class="btn btn-sm btn-outline-primary" :title="description"
                                @click="insertVariableInNewTemplate('{' + key + '}')">
                                <span x-text="'{' + key + '}'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="mb-1">
                    <label class="form-label fw-semibold">Mensaje</label>
                    <textarea class="form-control" rows="5" x-ref="newTemplateBodyInput" x-model="newTemplateBody"></textarea>
                    <div class="form-text">Las variables se reemplazan automáticamente por los datos de cada cita o recurrencia al enviar.</div>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary mb-2" @click="showNewTemplatePreview = !showNewTemplatePreview">
                    <span x-show="!showNewTemplatePreview">Previsualizar</span>
                    <span x-show="showNewTemplatePreview">Ocultar vista previa</span>
                </button>

                <div x-show="showNewTemplatePreview">
                    <div class="border rounded p-3 bg-light small mb-1" style="white-space: pre-wrap;" x-text="newTemplatePreview"></div>
                    <div class="form-text">Vista previa con datos de ejemplo — el mensaje real usará los datos de cada cita o mascota.</div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" @click="submitNewTemplate()" :disabled="creatingTemplate">
                    <span x-show="!creatingTemplate">Crear plantilla</span>
                    <span x-show="creatingTemplate">Creando…</span>
                </button>
            </div>
        </div>
    </div>
</div>
