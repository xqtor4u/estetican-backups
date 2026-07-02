import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es';

const is24h = document.body.dataset.time24h === '1';

document.querySelectorAll('[type="datetime-local"]').forEach(el => {
    // En campos de agendado (data-force-24h) se fuerza 24h para eliminar la
    // ambigüedad de parseo de AM/PM en español, que hacía que Flatpickr
    // rechazara silenciosamente la hora tecleada a mano y revirtiera el valor.
    const force24h = el.dataset.force24h === '1';
    const use24h = is24h || force24h;

    const options = {
        enableTime: true,
        time_24hr: use24h,
        minuteIncrement: 5,
        dateFormat: 'Y-m-dTH:i',       // valor enviado al servidor (oculto)
        altInput: true,                  // muestra un campo separado legible
        altFormat: use24h ? 'd/m/Y H:i' : 'd/m/Y h:i K',
        locale: Spanish,
        defaultDate: el.value || null,
        allowInput: true,
    };

    if (el.dataset.minTime) options.minTime = el.dataset.minTime;
    if (el.dataset.maxTime) options.maxTime = el.dataset.maxTime;

    flatpickr(el, options);
});
