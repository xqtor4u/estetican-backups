// ZEUS-047 — "Repetir esta cita" en el alta de cita del backoffice (agenda/create).
// Arma la vista previa de la serie con los datos del formulario completo (POST a
// pets.bookings.series-preview) para ver qué fechas quedan libres, recorridas o sin lugar
// antes de guardar. Guardar sigue siendo el submit normal del formulario.
export default function seriesRepeatFactory({ previewUrl, formId }) {
    return {
        enabled: false,
        mode: '30',
        intervalDays: 30,
        until: '1y',
        untilDate: '',
        loading: false,
        error: '',
        preview: null,

        init() {
            // Frecuencia sugerida por el servicio (services.recurrence_days): baño cada 30, vacuna anual…
            document.querySelectorAll('.service-card-input').forEach((cb) => {
                cb.addEventListener('change', () => this.suggestFromServices());
            });
            this.$watch('enabled', (on) => { if (on) this.suggestFromServices(); this.preview = null; });
            ['mode', 'intervalDays', 'until', 'untilDate'].forEach((k) => this.$watch(k, () => { this.preview = null; }));
        },

        suggestFromServices() {
            const days = [...document.querySelectorAll('.service-card-input:checked')]
                .map((cb) => parseInt(cb.dataset.recurrenceDays || '0', 10))
                .filter((d) => d > 0);
            if (!days.length) return;
            const d = Math.min(...days);
            if (['7', '15', '30'].includes(String(d))) {
                this.mode = String(d);
            } else {
                this.mode = 'custom';
                this.intervalDays = d;
            }
            if (d >= 180 && this.until === '6m') this.until = '1y';
        },

        get stats() {
            const items = this.preview?.items || [];
            return {
                total: items.length,
                ok: items.filter((i) => i.state === 'ok').length,
                moved: items.filter((i) => i.state === 'moved').length,
                skipped: items.filter((i) => i.state === 'skipped').length,
            };
        },

        async review() {
            this.loading = true;
            this.error = '';
            this.preview = null;
            try {
                const form = document.getElementById(formId);
                const res = await fetch(previewUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                    this.error = first || data.message || 'No se pudo revisar la serie.';
                    return;
                }
                this.preview = data;
            } catch (e) {
                this.error = 'No se pudo revisar la serie (sin conexión).';
            } finally {
                this.loading = false;
            }
        },
    };
}
