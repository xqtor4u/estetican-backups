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

    if (activePhoneInputs.length === 0 || activePhoneInputs.every((input) => input.value.trim() === '')) {
        warnings.push('Debe capturarse al menos un telefono activo.');
    }

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
        const html = `
            <div class="phone-item mb-3 border p-3">
                <div class="mb-2">
                    <label>Tipo:</label>
                    <select name="phones[${phoneIndex}][type]" class="form-control" required>
                        <option value="mobile">Móvil</option>
                        <option value="fixed">Fijo</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label>Número:</label>
                    <input type="text" name="phones[${phoneIndex}][number]" class="form-control" required>
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
                    <label>Nombre:</label>
                    <input type="text" name="pets[${petIndex}][name]" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label>Especie:</label>
                    <input type="text" name="pets[${petIndex}][species]" class="form-control" placeholder="Perro, Gato, Pajaro..." value="${escapeHtml(defaultSpecies)}">
                </div>
                <div class="mb-2">
                    <label>Raza:</label>
                    <input type="text" name="pets[${petIndex}][breed]" class="form-control">
                </div>
                <div class="mb-2">
                    <label>Fecha de nacimiento:</label>
                    <input type="date" name="pets[${petIndex}][birth_date]" class="form-control">
                </div>
                <div class="mb-2">
                    <label>Fecha de deceso:</label>
                    <input type="date" name="pets[${petIndex}][death_date]" class="form-control">
                </div>
                <div class="mb-2">
                    <label>Numero de chip:</label>
                    <input type="text" name="pets[${petIndex}][microchip_code]" class="form-control">
                </div>
                <div class="mb-2">
                    <label>Numero de tatuaje:</label>
                    <input type="text" name="pets[${petIndex}][tattoo_code]" class="form-control">
                </div>
                <div class="mb-2">
                    <label>Sexo:</label>
                    <select name="pets[${petIndex}][sex]" class="form-control">
                        <option value="">Seleccionar</option>
                        <option value="male">Macho</option>
                        <option value="female">Hembra</option>
                        <option value="unknown">No definido</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label>Color:</label>
                    <input type="text" name="pets[${petIndex}][coat_color]" class="form-control">
                </div>
                <div class="mb-2">
                    <label>Tamano:</label>
                    <select name="pets[${petIndex}][size]" class="form-control">
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
                    <label>Notas:</label>
                    <textarea name="pets[${petIndex}][notes]" class="form-control" rows="2"></textarea>
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
    };

    const confirmAddPhoneModal = () => {
        const type = document.getElementById('modalPhoneType').value;
        const number = document.getElementById('modalPhoneNumber').value;

        if (!number) {
            showPersistentWarning({
                title: 'Telefono incompleto',
                message: 'El telefono nuevo no se agrego todavia.',
                items: ['Captura el numero antes de confirmar el alta.'],
            });
            return;
        }

        const html = `
            <tr class="phone-item">
                <td>
                    <select name="phones[${phoneIndex}][type]" class="form-control" required>
                        <option value="mobile" ${type === 'mobile' ? 'selected' : ''}>Móvil</option>
                        <option value="fixed" ${type === 'fixed' ? 'selected' : ''}>Fijo</option>
                    </select>
                </td>
                <td><input type="text" name="phones[${phoneIndex}][number]" value="${number}" class="form-control" required></td>
                <td class="text-center align-middle">
                    <input type="checkbox" name="phones[${phoneIndex}][delete]" value="1">
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
                <td><input type="text" name="pets[${petIndex}][name]" value="${name}" class="form-control" required></td>
                <td><input type="text" name="pets[${petIndex}][species]" value="${species}" class="form-control"></td>
                <td><input type="text" name="pets[${petIndex}][breed]" value="${breed}" class="form-control"></td>
                <td><input type="date" name="pets[${petIndex}][birth_date]" value="${birthDate}" class="form-control"></td>
                <td><input type="date" name="pets[${petIndex}][death_date]" value="${deathDate}" class="form-control"></td>
                <td><input type="text" name="pets[${petIndex}][microchip_code]" value="${microchip}" class="form-control"></td>
                <td><input type="text" name="pets[${petIndex}][tattoo_code]" value="${tattoo}" class="form-control"></td>
                <td>
                    <select name="pets[${petIndex}][sex]" class="form-control">
                        <option value="male" ${sex === 'male' ? 'selected' : ''}>Macho</option>
                        <option value="female" ${sex === 'female' ? 'selected' : ''}>Hembra</option>
                        <option value="unknown" ${sex === 'unknown' || sex === '' ? 'selected' : ''}>No definido</option>
                    </select>
                </td>
                <td><input type="text" name="pets[${petIndex}][coat_color]" value="${coatColor}" class="form-control"></td>
                <td>
                    <select name="pets[${petIndex}][size]" class="form-control">
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
                    <input type="checkbox" name="pets[${petIndex}][is_sterilized]" value="1" ${sterilized ? 'checked' : ''}>
                </td>
                <td><textarea name="pets[${petIndex}][notes]" class="form-control" rows="2">${notes}</textarea></td>
                <td class="text-center align-middle">
                    <input type="checkbox" name="pets[${petIndex}][delete]" value="1">
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