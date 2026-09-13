import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es';

const is24h = document.body.dataset.time24h === '1';

// Pie con "Cancelar" / "Confirmar" para el pop-up del calendario. En tablet el
// desplegable de Flatpickr se cierra al tocar fuera y no era obvio cómo aceptar o
// descartar la selección — estos botones lo hacen explícito.
function attachFooter(instance) {
    if (instance._footerAttached) return;
    instance._footerAttached = true;

    const footer = document.createElement('div');
    footer.className = 'flatpickr-footer';

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'flatpickr-footer__btn flatpickr-footer__btn--cancel';
    cancel.textContent = 'Cancelar';
    cancel.addEventListener('click', () => {
        // Vuelve al valor que había al abrir el pop-up y cierra.
        instance.setDate(instance._openingValue || '', true, instance.config.dateFormat);
        instance.close();
    });

    const confirm = document.createElement('button');
    confirm.type = 'button';
    confirm.className = 'flatpickr-footer__btn flatpickr-footer__btn--confirm';
    confirm.textContent = 'Confirmar';
    confirm.addEventListener('click', () => instance.close());

    footer.appendChild(cancel);
    footer.appendChild(confirm);
    instance.calendarContainer.appendChild(footer);
}

document.querySelectorAll('[type="datetime-local"]').forEach(el => {
    // El formato de hora lo manda SIEMPRE el ajuste del sistema (Configuración ›
    // Formato de hora), igual que el resto del backoffice. En 12h se desactiva el
    // tecleo libre: antes estos campos forzaban 24h (data-force-24h, ya sin efecto)
    // porque al escribir "AM/PM" a mano en español Flatpickr revertía el valor en
    // silencio; con el calendario como única entrada esa ambigüedad no existe.
    const use24h = is24h;

    const options = {
        enableTime: true,
        time_24hr: use24h,
        minuteIncrement: 5,
        dateFormat: 'Y-m-dTH:i',       // valor enviado al servidor (oculto)
        altInput: true,                  // muestra un campo separado legible
        altFormat: use24h ? 'd/m/Y H:i' : 'd/m/Y h:i K',
        locale: Spanish,
        defaultDate: el.value || null,
        allowInput: use24h,
        onReady: (selectedDates, dateStr, instance) => {
            instance._openingValue = instance.input.value;
            attachFooter(instance);
        },
        onOpen: (selectedDates, dateStr, instance) => {
            instance._openingValue = instance.input.value;
        },
    };

    if (el.dataset.minTime) options.minTime = el.dataset.minTime;
    if (el.dataset.maxTime) options.maxTime = el.dataset.maxTime;

    flatpickr(el, options);
});
