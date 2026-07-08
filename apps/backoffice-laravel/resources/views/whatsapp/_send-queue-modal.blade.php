{{-- Modal de envío secuencial con vista previa del mensaje resuelto --}}
<div class="modal fade" id="modalSendQueue" x-ref="sendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Enviar recordatorios</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <template x-if="!isDone">
                    <div>
                        <p class="text-muted mb-1" x-text="`${queueIndex + 1} de ${queue.length}`"></p>
                        <h5 x-text="currentLabel"></h5>

                        <p class="small text-muted mt-2 mb-1" x-show="previewLoading">Generando vista previa del mensaje…</p>

                        <div class="alert alert-danger small mb-2" x-show="!previewLoading && previewError" x-text="previewError"></div>

                        <div class="border rounded p-3 bg-light small mb-2"
                             style="white-space: pre-wrap;"
                             x-show="!previewLoading && !previewError && previewMessage"
                             x-text="previewMessage"></div>

                        <p class="small text-muted">Se abrirá WhatsApp en una pestaña nueva con este mensaje ya redactado. Confirma el envío ahí.</p>
                    </div>
                </template>
                <template x-if="isDone">
                    <div class="text-center py-3">
                        <p class="mb-0 fw-semibold">Listo — recargando la bandeja…</p>
                    </div>
                </template>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-link link-secondary" @click="skipCurrent()" x-show="!isDone">Omitir</button>
                <button type="button" class="btn btn-success" @click="sendCurrent()" x-show="!isDone" :disabled="sending || previewLoading || previewError">
                    <span x-show="!sending">Abrir WhatsApp</span>
                    <span x-show="sending">Enviando…</span>
                </button>
            </div>
        </div>
    </div>
</div>
