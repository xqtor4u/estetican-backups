export default function whatsappPhoneLinkFactory({ phone, petId, templatesUrl, linkUrl, livePetsUrl }) {
    return {
        phone,
        petId: petId || null,
        templatesUrl,
        linkUrl,
        livePetsUrl,
        open: false,
        step: 'menu', // 'menu' | 'pets'
        loaded: false,
        templates: [],
        livePets: null,
        pendingTemplateId: null,
        sending: false,
        error: '',

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.step = 'menu';
                this.error = '';
                if (!this.loaded) this.fetchTemplates();
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

        async fetchLivePets() {
            if (this.livePets !== null) return this.livePets;
            try {
                const response = await fetch(this.livePetsUrl, { headers: { Accept: 'application/json' } });
                this.livePets = response.ok ? await response.json() : [];
            } catch (e) {
                this.livePets = [];
            }
            return this.livePets;
        },

        /**
         * Punto de entrada al elegir "Mensaje directo" (templateId null) o una plantilla. Si ya
         * sabemos qué mascota corresponde (`petId` fijo, ej. viene de una cita) o la plantilla no
         * es de contexto "general" (nunca usa {mascota}), se envía directo. Si es "general" y no
         * hay `petId` fijo, primero hay que ver cuántas mascotas vivas tiene el cliente — con más
         * de una no se puede adivinar cuál, así que se pregunta antes de enviar.
         */
        async choose(templateId, context) {
            if (this.sending) return;

            if (this.petId || !templateId || context !== 'general') {
                await this.doSend(templateId, this.petId);
                return;
            }

            this.sending = true;
            const pets = await this.fetchLivePets();
            this.sending = false;

            if (pets.length <= 1) {
                await this.doSend(templateId, null);
                return;
            }

            this.pendingTemplateId = templateId;
            this.step = 'pets';
        },

        async choosePet(petId) {
            await this.doSend(this.pendingTemplateId, petId);
        },

        async sendWithoutPet() {
            await this.doSend(this.pendingTemplateId, null);
        },

        async doSend(templateId, petId) {
            if (this.sending) return;

            this.sending = true;
            this.error = '';

            const params = new URLSearchParams({ phone: this.phone });
            if (templateId) params.set('template_id', templateId);
            if (petId) params.set('pet_id', petId);

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
