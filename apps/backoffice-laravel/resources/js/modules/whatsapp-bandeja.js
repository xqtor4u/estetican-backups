export default function whatsappBandejaFactory(config) {
    return {
        csrfToken: config.csrfToken,
        sendUrlTemplate: config.sendUrlTemplate,
        previewUrlTemplate: config.previewUrlTemplate,
        createTemplateUrl: config.createTemplateUrl,
        templateContext: config.templateContext,
        templateVariables: config.templateVariables ?? {},
        templates: config.templates ?? [],
        rowsById: config.rows.reduce((acc, r) => { acc[r.id] = r.label; return acc; }, {}),
        selected: [],
        templateId: config.defaultTemplateId ?? '',
        queue: [],
        queueIndex: 0,
        sending: false,
        previewLoading: false,
        previewMessage: '',
        previewError: '',
        modalInstance: null,
        createModalInstance: null,
        newTemplateName: '',
        newTemplateBody: '',
        creatingTemplate: false,
        createTemplateError: '',
        showNewTemplatePreview: false,

        toggleAll(checked, ids) {
            this.selected = checked ? [...ids] : [];
        },

        onTemplateSelectChange() {
            if (this.templateId === '__new__') {
                this.templateId = '';
                this.openCreateTemplate();
            }
        },

        openCreateTemplate() {
            this.newTemplateName = '';
            this.newTemplateBody = '';
            this.createTemplateError = '';
            this.showNewTemplatePreview = false;

            if (!this.createModalInstance) {
                this.createModalInstance = new bootstrap.Modal(this.$refs.createTemplateModal);
            }
            this.createModalInstance.show();
        },

        get newTemplatePreview() {
            const samples = this.templateContext === 'recurrencia'
                ? { cliente: 'Juan Pérez', mascota: 'Firulais', servicio: 'Baño y corte', ultima_fecha: '01/06/2026', dias_vencido: '15' }
                : { cliente: 'Juan Pérez', mascota: 'Firulais', servicio: 'Baño y corte', fecha: '10/07/2026', hora: '10:30 AM' };

            let result = this.newTemplateBody;
            for (const [key, value] of Object.entries(samples)) {
                result = result.split('{' + key + '}').join(value);
            }
            return result;
        },

        insertVariableInNewTemplate(variable) {
            const el = this.$refs.newTemplateBodyInput;
            const start = el.selectionStart;
            const end = el.selectionEnd;
            const text = this.newTemplateBody;
            this.newTemplateBody = text.slice(0, start) + variable + text.slice(end);
            this.$nextTick(() => {
                el.focus();
                el.selectionStart = el.selectionEnd = start + variable.length;
            });
        },

        async submitNewTemplate() {
            if (!this.newTemplateName.trim() || !this.newTemplateBody.trim()) {
                this.createTemplateError = 'Nombre y mensaje son obligatorios.';
                return;
            }

            this.creatingTemplate = true;
            this.createTemplateError = '';

            try {
                const response = await fetch(this.createTemplateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        name: this.newTemplateName,
                        body: this.newTemplateBody,
                        context: this.templateContext,
                        is_active: true,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    const errors = data.errors ? Object.values(data.errors).flat().join(' ') : null;
                    this.createTemplateError = errors || data.message || 'No se pudo crear la plantilla.';
                    this.creatingTemplate = false;
                    return;
                }

                this.templates.push(data.template);
                this.templateId = data.template.id;
                this.createModalInstance.hide();
            } catch (e) {
                this.createTemplateError = 'Error de red al crear la plantilla.';
            }

            this.creatingTemplate = false;
        },

        async openQueue() {
            if (!this.templateId) {
                alert('Selecciona una plantilla antes de enviar.');
                return;
            }
            if (this.selected.length === 0) {
                alert('Selecciona al menos una cita.');
                return;
            }

            this.queue = [...this.selected];
            this.queueIndex = 0;

            if (!this.modalInstance) {
                this.modalInstance = new bootstrap.Modal(this.$refs.sendModal);
            }
            this.modalInstance.show();
            await this.loadPreview();
        },

        get currentBookingId() {
            return this.queue[this.queueIndex] ?? null;
        },

        get currentLabel() {
            return this.rowsById[this.currentBookingId] ?? '';
        },

        get isDone() {
            return this.queueIndex >= this.queue.length;
        },

        async loadPreview() {
            if (this.isDone) return;

            this.previewMessage = '';
            this.previewError = '';
            this.previewLoading = true;

            const url = this.previewUrlTemplate.replace('__ID__', this.currentBookingId);

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ whatsapp_template_id: this.templateId }),
                });

                const data = await response.json();

                if (!response.ok) {
                    this.previewError = data.message ?? 'No se pudo generar la vista previa.';
                } else {
                    this.previewMessage = data.message;
                }
            } catch (e) {
                this.previewError = 'Error de red al generar la vista previa.';
            }

            this.previewLoading = false;
        },

        async sendCurrent() {
            if (this.isDone || this.sending || this.previewError) return;

            this.sending = true;
            const url = this.sendUrlTemplate.replace('__ID__', this.currentBookingId);

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ whatsapp_template_id: this.templateId }),
                });

                const data = await response.json();

                if (!response.ok) {
                    alert(data.message ?? 'No se pudo enviar el recordatorio.');
                    this.sending = false;
                    return;
                }

                window.open(data.wa_link, '_blank');
                await this.advance();
            } catch (e) {
                alert('Error de red al enviar el recordatorio.');
            }

            this.sending = false;
        },

        async skipCurrent() {
            await this.advance();
        },

        async advance() {
            this.queueIndex++;
            if (this.isDone) {
                setTimeout(() => window.location.reload(), 800);
            } else {
                await this.loadPreview();
            }
        },
    };
}
