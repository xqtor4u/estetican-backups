export default function whatsappBandejaFactory(config) {
    return {
        csrfToken: config.csrfToken,
        sendUrlTemplate: config.sendUrlTemplate,
        rowsById: config.rows.reduce((acc, r) => { acc[r.id] = r.label; return acc; }, {}),
        selected: [],
        templateId: config.defaultTemplateId ?? '',
        queue: [],
        queueIndex: 0,
        sending: false,
        modalInstance: null,

        toggleAll(checked, ids) {
            this.selected = checked ? [...ids] : [];
        },

        openQueue() {
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

        async sendCurrent() {
            if (this.isDone || this.sending) return;

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
                this.advance();
            } catch (e) {
                alert('Error de red al enviar el recordatorio.');
            }

            this.sending = false;
        },

        skipCurrent() {
            this.advance();
        },

        advance() {
            this.queueIndex++;
            if (this.isDone) {
                setTimeout(() => window.location.reload(), 800);
            }
        },
    };
}
