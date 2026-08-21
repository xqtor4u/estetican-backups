export default function whatsappPhoneLinkFactory({ phone, templatesUrl, linkUrl }) {
    return {
        phone,
        templatesUrl,
        linkUrl,
        open: false,
        loaded: false,
        templates: [],
        sending: false,
        error: '',

        toggle() {
            this.open = !this.open;
            if (this.open && !this.loaded) {
                this.fetchTemplates();
            }
        },

        async fetchTemplates() {
            try {
                const response = await fetch(this.templatesUrl, { headers: { Accept: 'application/json' } });
                this.templates = response.ok ? await response.json() : [];
            } catch (e) {
                this.templates = [];
            }
            this.loaded = true;
        },

        async send(templateId) {
            if (this.sending) return;

            this.sending = true;
            this.error = '';

            const params = new URLSearchParams({ phone: this.phone });
            if (templateId) params.set('template_id', templateId);

            try {
                const response = await fetch(`${this.linkUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();

                if (!response.ok) {
                    this.error = data.message ?? 'No se pudo generar el mensaje.';
                    this.sending = false;
                    return;
                }

                window.open(data.wa_link, '_blank');
                this.open = false;
            } catch (e) {
                this.error = 'Error de red. Intenta de nuevo.';
            }

            this.sending = false;
        },
    };
}
