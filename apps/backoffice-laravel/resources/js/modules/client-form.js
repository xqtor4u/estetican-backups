const openModal = (modalId) => {
    const bs = window.bootstrap;
    if (typeof bs === 'undefined' || !bs.Modal) {
        alert('Bootstrap Modal no está disponible. Verifica que el JS de Bootstrap se esté cargando correctamente.');
        return null;
    }

    const modalElement = document.getElementById(modalId);

    if (!modalElement) {
        return null;
    }

    const modal = new window.bootstrap.Modal(modalElement);
    modal.show();

    return modal;
};

const hideModalInstance = (modalId) => {
    const modalElement = document.getElementById(modalId);
    const bs = window.bootstrap;
    if (!modalElement || typeof bs === 'undefined' || !bs.Modal) {
        return;
    }

    bs.Modal.getInstance(modalElement)?.hide();
};

const countNodes = (selector) => document.querySelectorAll(selector).length;

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

const showPersistentWarning = ({
    title = 'Revisa la captura',
    message = 'Hay datos que deben corregirse antes de continuar.',
    items = [],
}) => {
    const bs = window.bootstrap;
    if (typeof bs === 'undefined' || !bs.Modal) {
        alert([message, ...items.map((item) => `- ${item}`)].join('\n'));
        return;
    }

    let modal = document.getElementById('clientValidationWarningModal');

    if (!modal) {
        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="clientValidationWarningModal" tabindex="-1" aria-labelledby="clientValidationWarningModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-warning">
                        <div class="modal-header">
                            <h5 class="modal-title" id="clientValidationWarningModalLabel"></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2" data-warning-message></p>
                            <ul class="mb-0 ps-3" data-warning-items></ul>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-warning" data-bs-dismiss="modal">Entendido</button>
                        </div>
                    </div>
                </div>
            </div>
        `);

        modal = document.getElementById('clientValidationWarningModal');
    }

    modal.querySelector('#clientValidationWarningModalLabel').textContent = title;
    modal.querySelector('[data-warning-message]').textContent = message;
    modal.querySelector('[data-warning-items]').innerHTML = items
        .map((item) => `<li>${escapeHtml(item)}</li>`)
        .join('');

    window.bootstrap.Modal.getOrCreateInstance(modal).show();
};

const movePhoneItem = (trigger) => {
    const item = trigger.closest('.phone-item');

    if (!item) {
        return false;
    }

    const direction = trigger.dataset.movePhone;
    const sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;

    if (!sibling || !sibling.classList.contains('phone-item')) {
        return false;
    }

    if (direction === 'up') {
        item.parentNode.insertBefore(item, sibling);
    } else {
        item.parentNode.insertBefore(sibling, item);
    }

    return true;
};

const digitsOnly = (value, maxLength) => value.replace(/\D+/g, '').slice(0, maxLength);

/* México/Norteamérica exige 10 dígitos exactos por default — activable desde Configuración →
   Clientes ("Permitir código de país en teléfonos") para zonas fronterizas o clientes de otro
   país, que amplía el rango a 8-15 dígitos (estándar internacional E.164). El form lleva estos
   límites en data-phone-min-digits/data-phone-max-digits (ver ClientController@create/edit). */
const phoneDigitRange = (input) => {
    const form = input.closest('form');

    return phoneDigitRangeOf(form);
};

const phoneDigitRangeOf = (form) => ({
    min: parseInt(form?.dataset.phoneMinDigits, 10) || 10,
    max: parseInt(form?.dataset.phoneMaxDigits, 10) || 10,
});

const phoneDigitsHintText = ({ min, max }) => (min === max ? `${min} dígitos` : `entre ${min} y ${max} dígitos`);

/* Filtra a solo dígitos mientras se escribe (nunca deja entrar letras/espacios/guiones) y marca
   el campo como inválido de inmediato si ya tiene contenido pero está fuera del rango permitido
   — sin esperar a que el usuario le dé "Actualizar"/"Crear cliente" para enterarse. */
const sanitizePhoneNumberInput = (input) => {
    const { min, max } = phoneDigitRange(input);
    const sanitized = digitsOnly(input.value, max);

    if (sanitized !== input.value) {
        input.value = sanitized;
    }

    input.classList.toggle('is-invalid', sanitized.length > 0 && (sanitized.length < min || sanitized.length > max));
};

const sanitizePhoneExtensionInput = (input) => {
    const sanitized = digitsOnly(input.value, 10);

    if (sanitized !== input.value) {
        input.value = sanitized;
    }
};

/* Delegado a nivel de formulario: cubre tanto los campos ya presentes al cargar la página como
   los agregados después dinámicamente (addPhone/confirmAddPhoneModal), sin tener que cablear
   cada input nuevo uno por uno. */
const attachLivePhoneValidation = (form) => {
    form.addEventListener('input', (event) => {
        if (event.target.matches('input[name^="phones["][name$="[number]"]')) {
            sanitizePhoneNumberInput(event.target);
        }

        if (event.target.matches('input[name^="phones["][name$="[extension]"]')) {
            sanitizePhoneExtensionInput(event.target);
        }
    });

    getPhoneNumberInputs(form).forEach(sanitizePhoneNumberInput);
};

const getPhoneNumberInputs = (form) => Array.from(form.querySelectorAll('input[name^="phones["][name$="[number]"]'));

const phoneInputIsMarkedForDelete = (input) => Boolean(
    input.closest('.phone-item')?.querySelector('input[type="checkbox"][name^="phones["][name$="[delete]"]')?.checked,
);

const collectClientFormWarnings = (form) => {
    const warnings = [];
    const nameField = form.querySelector('input[name="first_name"]');
    const emailField = form.querySelector('input[name="email"]');
    const submitter = form._lastSubmitter || null;
    const requiresPetForNextStep = submitter?.dataset.clientSubmitIntent === 'schedule-first-pet';

    if (!nameField || nameField.value.trim() === '') {
        warnings.push('El nombre del cliente es obligatorio.');
    }

    if (emailField && emailField.value.trim() !== '' && !emailField.checkValidity()) {
        warnings.push('Si capturas email, debe tener un formato valido.');
    }

    const activePhoneInputs = getPhoneNumberInputs(form)
        .filter((input) => !phoneInputIsMarkedForDelete(input));
    const nonEmptyPhoneInputs = activePhoneInputs.filter((input) => input.value.trim() !== '');

    if (nonEmptyPhoneInputs.length === 0) {
        warnings.push('Debe capturarse al menos un telefono activo.');
    }

    const phoneRange = phoneDigitRangeOf(form);

    nonEmptyPhoneInputs.forEach((input) => {
        const digits = input.value.replace(/\D+/g, '');

        if (digits.length < phoneRange.min || digits.length > phoneRange.max) {
            warnings.push(`El telefono "${input.value.trim()}" debe tener ${phoneDigitsHintText(phoneRange)}.`);
        }
    });

    if (requiresPetForNextStep) {
        const petNameInputs = Array.from(form.querySelectorAll('input[name^="pets["][name$="[name]"]'));
        const hasPet = petNameInputs.some((input) => input.value.trim() !== '');

        if (!hasPet) {
            warnings.push('Debes capturar al menos una mascota para continuar directo a agenda.');
        }
    }

    return warnings;
};

const getAddressCaptureDefaults = () => ({
    city: document.body?.dataset.addressDefaultCity || '',
    state: document.body?.dataset.addressDefaultState || '',
    country: document.body?.dataset.addressDefaultCountry || 'México',
});

const initClientCreateForm = () => {
    const form = document.getElementById('client-create-form');

    if (!form || form.dataset.clientCreateInitialized === 'true') {
        return;
    }

    form.dataset.clientCreateInitialized = 'true';

    attachLivePhoneValidation(form);

    let addressIndex = countNodes('#addresses .address-item');
    let phoneIndex = countNodes('#phones .phone-item');
    let petIndex = countNodes('#pets .pet-item');

    const addAddress = () => {
        const template = document.getElementById('client-address-template');

        if (!template) {
            return;
        }

        const html = template.innerHTML
            .replaceAll('__INDEX__', addressIndex)
            .replaceAll('__TITLE_INDEX__', addressIndex + 1);

        document.getElementById('addresses')?.insertAdjacentHTML('beforeend', html);
        window.EstetiCANAddressEditor?.init(document.querySelector(`#addresses .address-item[data-address-index="${addressIndex}"]`));
        addressIndex += 1;
    };

    const addPhone = () => {
        const phoneRange = phoneDigitRangeOf(form);
        const phoneHint = phoneDigitsHintText(phoneRange);

        const html = `
            <div class="phone-item mb-3 border p-3">
                <div class="d-flex justify-content-end gap-1 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-phone="up" title="Subir importancia">&uarr;</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-phone="down" title="Bajar importancia">&darr;</button>
                </div>
                <div class="mb-2">
                    <label for="phone-type-${phoneIndex}">Tipo:</label>
                    <select id="phone-type-${phoneIndex}" name="phones[${phoneIndex}][type]" class="form-control" required>
                        <option value="mobile">Móvil</option>
                        <option value="home">Casa</option>
                        <option value="work">Trabajo</option>
                        <option value="other">Otro</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="phone-number-${phoneIndex}">Número (${phoneHint}):</label>
                    <input id="phone-number-${phoneIndex}" type="text" inputmode="numeric" name="phones[${phoneIndex}][number]" class="form-control" required maxlength="${phoneRange.max}">
                    <div class="invalid-feedback">Debe tener ${phoneHint}.</div>
                </div>
                <div class="mb-2">
                    <label for="phone-extension-${phoneIndex}">Ext. (opcional):</label>
                    <input id="phone-extension-${phoneIndex}" type="text" inputmode="numeric" name="phones[${phoneIndex}][extension]" class="form-control" maxlength="10">
                </div>
            </div>
        `;

        document.getElementById('phones')?.insertAdjacentHTML('beforeend', html);
        phoneIndex += 1;
    };

    const addPet = () => {
        const defaultSpecies = document.body?.dataset.petDefaultSpecies || form.dataset.petDefaultSpecies || '';
        const html = `
            <div class="pet-item mb-3 border p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Perro</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-dynamic-pet="true">Quitar</button>
                </div>
                <div class="mb-2">
                    <label for="pet-name-${petIndex}">Nombre:</label>
                    <input id="pet-name-${petIndex}" type="text" name="pets[${petIndex}][name]" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label for="pet-species-${petIndex}">Especie:</label>
                    <input id="pet-species-${petIndex}" type="text" name="pets[${petIndex}][species]" class="form-control" placeholder="Perro, Gato, Pajaro..." value="${escapeHtml(defaultSpecies)}">
                </div>
                <div class="mb-2">
                    <label for="pet-breed-${petIndex}">Raza:</label>
                    <input id="pet-breed-${petIndex}" type="text" name="pets[${petIndex}][breed]" class="form-control">
                </div>
                <div class="mb-2">
                    <label for="pet-birth-date-${petIndex}">Fecha de nacimiento:</label>
                    <input id="pet-birth-date-${petIndex}" type="date" name="pets[${petIndex}][birth_date]" class="form-control">
                </div>
                <div class="mb-2">
                    <label for="pet-death-date-${petIndex}">Fecha de deceso:</label>
                    <input id="pet-death-date-${petIndex}" type="date" name="pets[${petIndex}][death_date]" class="form-control">
                </div>
                <div class="mb-2">
                    <label for="pet-microchip-${petIndex}">Numero de chip:</label>
                    <input id="pet-microchip-${petIndex}" type="text" name="pets[${petIndex}][microchip_code]" class="form-control">
                </div>
                <div class="mb-2">
                    <label for="pet-tattoo-${petIndex}">Numero de tatuaje:</label>
                    <input id="pet-tattoo-${petIndex}" type="text" name="pets[${petIndex}][tattoo_code]" class="form-control">
                </div>
                <div class="mb-2">
                    <label for="pet-sex-${petIndex}">Sexo:</label>
                    <select id="pet-sex-${petIndex}" name="pets[${petIndex}][sex]" class="form-control">
                        <option value="">Seleccionar</option>
                        <option value="male">Macho</option>
                        <option value="female">Hembra</option>
                        <option value="unknown">No definido</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="pet-coat-color-${petIndex}">Color:</label>
                    <input id="pet-coat-color-${petIndex}" type="text" name="pets[${petIndex}][coat_color]" class="form-control">
                </div>
                <div class="mb-2">
                    <label for="pet-size-${petIndex}">Tamano:</label>
                    <select id="pet-size-${petIndex}" name="pets[${petIndex}][size]" class="form-control">
                        <option value="">Seleccionar</option>
                        <option value="mini">Mini</option>
                        <option value="small">Pequeno</option>
                        <option value="medium">Mediano</option>
                        <option value="large">Grande</option>
                        <option value="giant">Gigante</option>
                    </select>
                </div>
                <div class="form-check mb-2">
                    <input type="hidden" name="pets[${petIndex}][is_sterilized]" value="0">
                    <input class="form-check-input" type="checkbox" name="pets[${petIndex}][is_sterilized]" value="1" id="pet-sterilized-${petIndex}">
                    <label class="form-check-label" for="pet-sterilized-${petIndex}">Esterilizado</label>
                </div>
                <div class="mb-2">
                    <label for="pet-notes-${petIndex}">Notas:</label>
                    <textarea id="pet-notes-${petIndex}" name="pets[${petIndex}][notes]" class="form-control" rows="2"></textarea>
                </div>
            </div>
        `;

        document.getElementById('pets')?.insertAdjacentHTML('beforeend', html);
        petIndex += 1;
    };

    form.addEventListener('click', (event) => {
        const submitButton = event.target.closest('button[type="submit"]');

        if (submitButton) {
            form._lastSubmitter = submitButton;
        }
    }, true);

    form.addEventListener('submit', (event) => {
        const warnings = collectClientFormWarnings(form);

        if (warnings.length > 0) {
            event.preventDefault();
            showPersistentWarning({
                title: 'No se pudo crear el cliente',
                message: 'Corrige o completa la captura antes de guardar.',
                items: warnings,
            });
        }
    });

    form.addEventListener('click', (event) => {
        const moveTrigger = event.target.closest('[data-move-phone]');

        if (moveTrigger) {
            movePhoneItem(moveTrigger);

            return;
        }

        const actionTrigger = event.target.closest('[data-client-create-action]');

        if (actionTrigger) {
            const action = actionTrigger.dataset.clientCreateAction;

            if (action === 'add-address') {
                addAddress();
            }

            if (action === 'add-phone') {
                addPhone();
            }

            if (action === 'add-pet') {
                addPet();
            }

            return;
        }

        const removeTrigger = event.target.closest('[data-remove-dynamic-pet]');

        if (removeTrigger) {
            removeTrigger.closest('.pet-item')?.remove();
        }
    });
};

const initClientEditForm = () => {
    const form = document.getElementById('client-edit-form');

    if (!form || form.dataset.clientEditInitialized === 'true') {
        return;
    }

    form.dataset.clientEditInitialized = 'true';

    attachLivePhoneValidation(form);

    // El modal "Agregar teléfono" vive fuera de la fila de la tabla (sus campos no tienen
    // name="phones[...]", los lee confirmAddPhoneModal por id) — necesita su propio cableado,
    // el delegado de attachLivePhoneValidation no lo alcanza.
    document.getElementById('modalPhoneNumber')?.addEventListener('input', (event) => sanitizePhoneNumberInput(event.target));
    document.getElementById('modalPhoneExtension')?.addEventListener('input', (event) => sanitizePhoneExtensionInput(event.target));

    let addressIndex = countNodes('#addresses .address-item');
    let phoneIndex = countNodes('#phones .phone-item');
    let petIndex = countNodes('#pets .pet-item');
    let formChanged = false;
    let isSubmitting = false;
    let pendingRedirect = null;

    const markFormChanged = () => {
        formChanged = true;
    };

    const showAddressModal = () => {
        if (!openModal('addressModal')) {
            return;
        }

        const addressDefaults = getAddressCaptureDefaults();

        document.getElementById('modalAddressType').value = 'home';
        document.getElementById('modalAddressStreet').value = '';
        document.getElementById('modalAddressExteriorNumber').value = '';
        document.getElementById('modalAddressInteriorNumber').value = '';
        document.getElementById('modalAddressColonia').value = '';
        document.getElementById('modalAddressCity').value = addressDefaults.city;
        document.getElementById('modalAddressState').value = addressDefaults.state;
        document.getElementById('modalAddressZip').value = '';
        document.getElementById('modalAddressCountry').value = addressDefaults.country;
        document.getElementById('modalAddressLat').value = '';
        document.getElementById('modalAddressLng').value = '';
    };

    const confirmAddAddressModal = () => {
        const type = document.getElementById('modalAddressType').value;
        const street = document.getElementById('modalAddressStreet').value;
        const exteriorNumber = document.getElementById('modalAddressExteriorNumber').value;
        const interiorNumber = document.getElementById('modalAddressInteriorNumber').value;
        const colonia = document.getElementById('modalAddressColonia').value;
        const city = document.getElementById('modalAddressCity').value;
        const state = document.getElementById('modalAddressState').value;
        const zip = document.getElementById('modalAddressZip').value;
        const country = document.getElementById('modalAddressCountry').value;
        const lat = document.getElementById('modalAddressLat').value;
        const lng = document.getElementById('modalAddressLng').value;

        if (!type || !street || !city || !country) {
            showPersistentWarning({
                title: 'Direccion incompleta',
                message: 'La direccion nueva no se agrego todavia.',
                items: ['Completa tipo, calle, ciudad y pais antes de confirmar.'],
            });
            return;
        }

        const template = document.getElementById('client-address-template').innerHTML
            .replaceAll('__INDEX__', addressIndex)
            .replaceAll('__TITLE_INDEX__', addressIndex + 1);

        document.getElementById('addresses').insertAdjacentHTML('beforeend', template);

        const addressCard = document.querySelector(`#addresses .address-item[data-address-index="${addressIndex}"]`);
        addressCard.querySelector('.address-editor-type').value = type;
        addressCard.querySelector('.address-editor-street').value = street;
        addressCard.querySelector('.address-editor-exterior').value = exteriorNumber;
        addressCard.querySelector('.address-editor-interior').value = interiorNumber;
        addressCard.querySelector('.address-editor-colonia').value = colonia;
        addressCard.querySelector('.address-editor-city').value = city;
        addressCard.querySelector('.address-editor-state').value = state;
        addressCard.querySelector('.address-editor-zip').value = zip;
        addressCard.querySelector('.address-editor-country').value = country;
        addressCard.querySelector('.address-editor-lat').value = lat;
        addressCard.querySelector('.address-editor-lng').value = lng;
        window.EstetiCANAddressEditor?.init(addressCard);
        addressIndex += 1;
        hideModalInstance('addressModal');
        markFormChanged();
    };

    const showPhoneModal = () => {
        if (!openModal('phoneModal')) {
            return;
        }

        document.getElementById('modalPhoneType').value = 'mobile';
        document.getElementById('modalPhoneNumber').value = '';
        document.getElementById('modalPhoneExtension').value = '';
    };

    const confirmAddPhoneModal = () => {
        const type = document.getElementById('modalPhoneType').value;
        const number = document.getElementById('modalPhoneNumber').value;
        const extension = document.getElementById('modalPhoneExtension').value;
        const phoneRange = phoneDigitRangeOf(form);
        const phoneHint = phoneDigitsHintText(phoneRange);

        if (!number || number.length < phoneRange.min || number.length > phoneRange.max) {
            showPersistentWarning({
                title: 'Telefono incompleto',
                message: 'El telefono nuevo no se agrego todavia.',
                items: [number ? `El numero debe tener ${phoneHint}.` : 'Captura el numero antes de confirmar el alta.'],
            });
            return;
        }

        const html = `
            <tr class="phone-item">
                <td>
                    <select name="phones[${phoneIndex}][type]" class="form-control" required aria-labelledby="phones-th-type">
                        <option value="mobile" ${type === 'mobile' ? 'selected' : ''}>Móvil</option>
                        <option value="home" ${type === 'home' ? 'selected' : ''}>Casa</option>
                        <option value="work" ${type === 'work' ? 'selected' : ''}>Trabajo</option>
                        <option value="other" ${type === 'other' ? 'selected' : ''}>Otro</option>
                    </select>
                </td>
                <td>
                    <input type="text" inputmode="numeric" name="phones[${phoneIndex}][number]" value="${number}" class="form-control" required maxlength="${phoneRange.max}" aria-labelledby="phones-th-number">
                    <div class="invalid-feedback">Debe tener ${phoneHint}.</div>
                </td>
                <td><input type="text" inputmode="numeric" name="phones[${phoneIndex}][extension]" value="${extension}" class="form-control" maxlength="10" aria-labelledby="phones-th-extension"></td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-phone="up" title="Subir importancia">&uarr;</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-phone="down" title="Bajar importancia">&darr;</button>
                </td>
                <td class="text-center align-middle">
                    <input type="checkbox" name="phones[${phoneIndex}][delete]" value="1" aria-labelledby="phones-th-delete">
                </td>
            </tr>
        `;

        document.querySelector('#phones tbody').insertAdjacentHTML('beforeend', html);
        phoneIndex += 1;
        hideModalInstance('phoneModal');
        markFormChanged();
    };

    const showPetModal = () => {
        if (!openModal('petModal')) {
            return;
        }

        const defaultSpecies = document.body?.dataset.petDefaultSpecies || document.getElementById('client-edit-form')?.dataset?.petDefaultSpecies || '';
        document.getElementById('modalPetName').value = '';
        document.getElementById('modalPetSpecies').value = defaultSpecies;
        document.getElementById('modalPetBreed').value = '';
        document.getElementById('modalPetBirthDate').value = '';
        document.getElementById('modalPetDeathDate').value = '';
        document.getElementById('modalPetMicrochip').value = '';
        document.getElementById('modalPetTattoo').value = '';
        document.getElementById('modalPetSex').value = 'unknown';
        document.getElementById('modalPetCoatColor').value = '';
        document.getElementById('modalPetSize').value = '';
        document.getElementById('modalPetSterilized').checked = false;
        document.getElementById('modalPetNotes').value = '';
    };

    const confirmAddPetModal = () => {
        const name = document.getElementById('modalPetName').value;
        const species = document.getElementById('modalPetSpecies').value;
        const breed = document.getElementById('modalPetBreed').value;
        const birthDate = document.getElementById('modalPetBirthDate').value;
        const deathDate = document.getElementById('modalPetDeathDate').value;
        const microchip = document.getElementById('modalPetMicrochip').value;
        const tattoo = document.getElementById('modalPetTattoo').value;
        const sex = document.getElementById('modalPetSex').value;
        const coatColor = document.getElementById('modalPetCoatColor').value;
        const size = document.getElementById('modalPetSize').value;
        const sterilized = document.getElementById('modalPetSterilized').checked;
        const notes = document.getElementById('modalPetNotes').value;

        if (!name) {
            showPersistentWarning({
                title: 'Mascota incompleta',
                message: 'La mascota nueva no se agrego todavia.',
                items: ['Captura al menos el nombre de la mascota antes de confirmar.'],
            });
            return;
        }

        const html = `
            <tr class="pet-item">
                <td><input type="text" name="pets[${petIndex}][name]" value="${name}" class="form-control" required aria-labelledby="pets-th-name"></td>
                <td><input type="text" name="pets[${petIndex}][species]" value="${species}" class="form-control" aria-labelledby="pets-th-species"></td>
                <td><input type="text" name="pets[${petIndex}][breed]" value="${breed}" class="form-control" aria-labelledby="pets-th-breed"></td>
                <td><input type="date" name="pets[${petIndex}][birth_date]" value="${birthDate}" class="form-control" aria-labelledby="pets-th-birth-date"></td>
                <td><input type="date" name="pets[${petIndex}][death_date]" value="${deathDate}" class="form-control" aria-labelledby="pets-th-death-date"></td>
                <td><input type="text" name="pets[${petIndex}][microchip_code]" value="${microchip}" class="form-control" aria-labelledby="pets-th-microchip"></td>
                <td><input type="text" name="pets[${petIndex}][tattoo_code]" value="${tattoo}" class="form-control" aria-labelledby="pets-th-tattoo"></td>
                <td>
                    <select name="pets[${petIndex}][sex]" class="form-control" aria-labelledby="pets-th-sex">
                        <option value="male" ${sex === 'male' ? 'selected' : ''}>Macho</option>
                        <option value="female" ${sex === 'female' ? 'selected' : ''}>Hembra</option>
                        <option value="unknown" ${sex === 'unknown' || sex === '' ? 'selected' : ''}>No definido</option>
                    </select>
                </td>
                <td><input type="text" name="pets[${petIndex}][coat_color]" value="${coatColor}" class="form-control" aria-labelledby="pets-th-coat-color"></td>
                <td>
                    <select name="pets[${petIndex}][size]" class="form-control" aria-labelledby="pets-th-size">
                        <option value="" ${size === '' ? 'selected' : ''}>Seleccionar</option>
                        <option value="mini" ${size === 'mini' ? 'selected' : ''}>Mini</option>
                        <option value="small" ${size === 'small' ? 'selected' : ''}>Pequeno</option>
                        <option value="medium" ${size === 'medium' ? 'selected' : ''}>Mediano</option>
                        <option value="large" ${size === 'large' ? 'selected' : ''}>Grande</option>
                        <option value="giant" ${size === 'giant' ? 'selected' : ''}>Gigante</option>
                    </select>
                </td>
                <td class="text-center align-middle">
                    <input type="hidden" name="pets[${petIndex}][is_sterilized]" value="0">
                    <input type="checkbox" name="pets[${petIndex}][is_sterilized]" value="1" ${sterilized ? 'checked' : ''} aria-labelledby="pets-th-sterilized">
                </td>
                <td><textarea name="pets[${petIndex}][notes]" class="form-control" rows="2" aria-labelledby="pets-th-notes">${notes}</textarea></td>
                <td class="text-center align-middle">
                    <input type="checkbox" name="pets[${petIndex}][delete]" value="1" aria-labelledby="pets-th-delete">
                </td>
            </tr>
        `;

        document.querySelector('#pets tbody').insertAdjacentHTML('beforeend', html);
        petIndex += 1;
        hideModalInstance('petModal');
        markFormChanged();

        // Flujo de alta rápida ("Nueva mascota" → clients.index?mode=pet_creation → aquí con
        // ?open_pet_modal=1): a diferencia de editar un cliente existente, donde tiene sentido
        // acumular varios cambios antes de un solo "Actualizar", aquí el botón promete un alta
        // directa — se guarda de inmediato para que la mascota exista de verdad y aparezca en
        // el panel de abajo ("Seleccionar mascota para gestionar tablas dependientes").
        if (new URLSearchParams(window.location.search).get('open_pet_modal')) {
            form.requestSubmit();
        }
    };

    form.addEventListener('input', markFormChanged, true);
    form.addEventListener('change', markFormChanged, true);
    form.addEventListener('click', (event) => {
        const moveTrigger = event.target.closest('[data-move-phone]');

        if (moveTrigger) {
            if (movePhoneItem(moveTrigger)) {
                markFormChanged();
            }

            return;
        }

        const trigger = event.target.closest('[data-client-edit-action]');

        if (!trigger) {
            return;
        }

        const action = trigger.dataset.clientEditAction;

        if (action === 'show-address-modal') {
            showAddressModal();
        }

        if (action === 'confirm-address-modal') {
            confirmAddAddressModal();
        }

        if (action === 'show-phone-modal') {
            showPhoneModal();
        }

        if (action === 'confirm-phone-modal') {
            confirmAddPhoneModal();
        }

        if (action === 'show-pet-modal') {
            showPetModal();
        }

        if (action === 'confirm-pet-modal') {
            confirmAddPetModal();
        }
    });

    if (new URLSearchParams(window.location.search).get('open_pet_modal')) {
        showPetModal();
    }

    form.addEventListener('submit', (event) => {
        const warnings = collectClientFormWarnings(form);

        if (warnings.length > 0) {
            event.preventDefault();
            showPersistentWarning({
                title: 'No se pudo guardar el cliente',
                message: 'Corrige o completa la captura antes de guardar.',
                items: warnings,
            });
            return;
        }

        isSubmitting = true;
        formChanged = false;
    });

    document.body.addEventListener('click', (event) => {
        const target = event.target.closest('a.btn-outline-secondary, a.btn-secondary');

        if (target && formChanged) {
            event.preventDefault();
            showUnsavedModal(target.getAttribute('href'));
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (formChanged && !isSubmitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    const showUnsavedModal = (redirectUrl) => {
        let modal = document.getElementById('unsavedModal');

        if (!modal) {
            const html = `
                <div class="modal fade" id="unsavedModal" tabindex="-1" aria-labelledby="unsavedModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="unsavedModalLabel">Cambios no guardados</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                Tienes cambios sin guardar. ¿Qué deseas hacer?
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-success" id="unsavedSaveBtn">Guardar y salir</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Seguir editando</button>
                                <button type="button" class="btn btn-danger" id="unsavedLeaveBtn">Salir sin guardar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', html);
            modal = document.getElementById('unsavedModal');
        }

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        document.getElementById('unsavedSaveBtn').onclick = () => {
            isSubmitting = true;
            formChanged = false;
            pendingRedirect = redirectUrl;
            bsModal.hide();

            setTimeout(() => {
                const formData = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    },
                    body: formData,
                })
                    .then(async (response) => {
                        if (response.ok) {
                            window.location.href = pendingRedirect;
                        } else if (response.status === 422) {
                            isSubmitting = false;
                            const data = await response.json();
                            const warnings = [];
                            for (const key in data.errors) {
                                warnings.push(...data.errors[key]);
                            }
                            showPersistentWarning({
                                title: 'No se pudo guardar',
                                message: 'El cliente sigue abierto para que corrijas la captura.',
                                items: warnings,
                            });
                        } else {
                            isSubmitting = false;
                            showPersistentWarning({
                                title: 'Error inesperado',
                                message: 'No se pudo guardar el cliente por un error no esperado.',
                            });
                        }

                        pendingRedirect = null;
                    })
                    .catch(() => {
                        isSubmitting = false;
                        showPersistentWarning({
                            title: 'Error de red o servidor',
                            message: 'La captura sigue abierta. Revisa la conexion o vuelve a intentar.',
                        });
                        pendingRedirect = null;
                    });
            }, 300);
        };

        document.getElementById('unsavedLeaveBtn').onclick = () => {
            formChanged = false;
            pendingRedirect = null;
            bsModal.hide();
            window.location.href = redirectUrl;
        };
    };
};

document.addEventListener('DOMContentLoaded', () => {
    initClientCreateForm();
    initClientEditForm();
});