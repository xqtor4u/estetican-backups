import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es';

const is24h = document.body.dataset.time24h === '1';

document.querySelectorAll('[type="datetime-local"]').forEach(el => {
    flatpickr(el, {
        enableTime: true,
        time_24hr: is24h,
        dateFormat: 'Y-m-dTH:i',       // valor enviado al servidor (oculto)
        altInput: true,                  // muestra un campo separado legible
        altFormat: is24h ? 'd/m/Y H:i' : 'd/m/Y h:i K',
        locale: Spanish,
        defaultDate: el.value || null,
        allowInput: true,
    });
});
