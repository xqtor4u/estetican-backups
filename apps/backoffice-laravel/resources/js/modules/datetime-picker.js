import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es';

const is24h = document.body.dataset.time24h === '1';

flatpickr('[type="datetime-local"]', {
    enableTime: true,
    time_24hr: is24h,
    dateFormat: 'Y-m-dTH:i',
    locale: Spanish,
});
