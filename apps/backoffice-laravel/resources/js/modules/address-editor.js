const parseCoordinatesFromText = (value) => {
    const text = (value || '').trim();

    if (text === '') {
        return null;
    }

    const patterns = [
        /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
        /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,
        /[?&]q=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
        /[?&]query=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
        /^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/,
    ];

    for (const pattern of patterns) {
        const match = text.match(pattern);

        if (!match) {
            continue;
        }

        const latitude = Number(match[1]);
        const longitude = Number(match[2]);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            continue;
        }

        if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
            continue;
        }

        return {
            lat: latitude.toFixed(8),
            lng: longitude.toFixed(8),
        };
    }

    return null;
};

const getFields = (root) => ({
    street:   root.querySelector('.address-editor-street'),
    exterior: root.querySelector('.address-editor-exterior'),
    interior: root.querySelector('.address-editor-interior'),
    colonia:  root.querySelector('.address-editor-colonia'),
    city:     root.querySelector('.address-editor-city'),
    state:    root.querySelector('.address-editor-state'),
    zip:      root.querySelector('.address-editor-zip'),
    country:  root.querySelector('.address-editor-country'),
    lat:      root.querySelector('.address-editor-lat'),
    lng:      root.querySelector('.address-editor-lng'),
});

const buildAddress = (fields) => {
    const streetLine = [fields.street?.value, fields.exterior?.value]
        .map((v) => (v || '').trim())
        .filter((v) => v !== '')
        .join(' ');

    const interior = (fields.interior?.value || '').trim();

    return [
        streetLine !== '' ? (interior !== '' ? `${streetLine} Int ${interior}` : streetLine) : '',
        fields.colonia?.value,
        fields.city?.value,
        fields.state?.value,
        fields.zip?.value,
        fields.country?.value,
    ]
        .map((v) => (v || '').trim())
        .filter((v) => v !== '')
        .join(', ');
};

const hasCoordinates = (fields) =>
    (fields.lat?.value || '').trim() !== '' && (fields.lng?.value || '').trim() !== '';

const hasMinimumAddress = (fields) =>
    (fields.street?.value || '').trim() !== '' && (fields.city?.value || '').trim() !== '';

const updateMapsButton = (root) => {
    const fields     = getFields(root);
    const mapsButton = root.querySelector('.address-editor-maps-btn');
    const statusNode = root.querySelector('.address-editor-status');

    if (!mapsButton || !statusNode) {
        return;
    }

    if (hasCoordinates(fields)) {
        mapsButton.href = 'https://www.google.com/maps?q=' + encodeURIComponent(`${fields.lat.value.trim()},${fields.lng.value.trim()}`);
        mapsButton.classList.remove('disabled');
        mapsButton.setAttribute('aria-disabled', 'false');
        statusNode.textContent = '';
        return;
    }

    if (!hasMinimumAddress(fields)) {
        mapsButton.href = '#';
        mapsButton.classList.add('disabled');
        mapsButton.setAttribute('aria-disabled', 'true');
        statusNode.textContent = 'Captura al menos calle y ciudad para buscar la dirección con precisión.';
        return;
    }

    mapsButton.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(buildAddress(fields));
    mapsButton.classList.remove('disabled');
    mapsButton.setAttribute('aria-disabled', 'false');
    statusNode.textContent = 'Google Maps abrirá una búsqueda con la dirección capturada para que puedas validar la ubicación.';
};

// Selector list for fields that affect the maps button link
const ADDRESS_FIELD_CLASSES = [
    '.address-editor-street',
    '.address-editor-exterior',
    '.address-editor-interior',
    '.address-editor-colonia',
    '.address-editor-city',
    '.address-editor-state',
    '.address-editor-zip',
    '.address-editor-country',
    '.address-editor-lat',
    '.address-editor-lng',
];

// ── Delegated: input on any address field ────────────────────────────────────
document.addEventListener('input', (event) => {
    if (!ADDRESS_FIELD_CLASSES.some((cls) => event.target.matches(cls))) {
        return;
    }
    const root = event.target.closest('[data-address-editor]');
    if (root) {
        updateMapsButton(root);
    }
});

// ── Delegated: change on lat/lng (decimal truncation warning) ────────────────
document.addEventListener('change', (event) => {
    if (!event.target.matches('.address-editor-lat, .address-editor-lng')) {
        return;
    }
    const root = event.target.closest('[data-address-editor]');
    if (!root) {
        return;
    }

    const field = event.target;
    const val = (field.value || '').trim();
    if (!val) {
        return;
    }

    const parts = val.split('.');
    if (parts.length === 2 && parts[1].length > 8) {
        if (confirm('Google proporciona coordenadas con demasiados decimales y el sistema no permite guardarlos tan largos. ¿Deseas que los ajustemos automáticamente al límite permitido?')) {
            field.value = Number(val).toFixed(8);
            updateMapsButton(root);
        }
    }
});

// ── Delegated: paste on lat/lng (smart coordinate split) ─────────────────────
document.addEventListener('paste', (event) => {
    if (!event.target.matches('.address-editor-lat, .address-editor-lng')) {
        return;
    }
    const root = event.target.closest('[data-address-editor]');
    if (!root) {
        return;
    }

    const pastedText = (event.clipboardData || window.clipboardData).getData('text');
    const parsed = parseCoordinatesFromText(pastedText);

    if (!parsed) {
        return;
    }

    event.preventDefault();

    const fields = getFields(root);
    if (fields.lat) fields.lat.value = parsed.lat;
    if (fields.lng) fields.lng.value = parsed.lng;

    updateMapsButton(root);

    const statusNode = root.querySelector('.address-editor-status');
    if (statusNode) {
        statusNode.textContent = 'Coordenadas detectadas. Recuerda guardar el formulario.';
    }

    flashCoordFields(fields);
});

// ── Scroll + flash lat/lng fields after coords are set ───────────────────────
const flashCoordFields = (fields) => {
    const latEl = fields.lat;
    const lngEl = fields.lng;
    if (!latEl) return;

    latEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

    [latEl, lngEl].filter(Boolean).forEach((el) => {
        el.style.transition = 'outline 0s';
        el.style.outline = '2px solid #0d6efd';
        setTimeout(() => {
            el.style.outline = '';
        }, 2000);
    });
};

// ── Delegated: click on maps button (prevent when disabled) ──────────────────
document.addEventListener('click', (event) => {
    const mapsBtn = event.target.closest('.address-editor-maps-btn');
    if (mapsBtn && mapsBtn.classList.contains('disabled')) {
        event.preventDefault();
    }
});

// ── Delegated: click on geocode button ───────────────────────────────────────
document.addEventListener('click', async (event) => {
    const geocodeBtn = event.target.closest('.address-editor-geocode-btn');
    if (!geocodeBtn) {
        return;
    }

    const root = geocodeBtn.closest('[data-address-editor]');
    if (!root) {
        return;
    }

    const fields     = getFields(root);
    const statusNode = root.querySelector('.address-editor-status');

    if (!hasMinimumAddress(fields)) {
        if (statusNode) statusNode.textContent = 'Captura al menos calle y ciudad antes de intentar traer coordenadas.';
        return;
    }

    geocodeBtn.disabled = true;
    if (statusNode) statusNode.textContent = 'Buscando coordenadas aproximadas...';

    try {
        const address  = buildAddress(fields);
        const response = await fetch(
            `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(address)}`,
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('No se pudo consultar el servicio de geocodificación.');
        }

        const results = await response.json();

        if (!Array.isArray(results) || results.length === 0) {
            if (statusNode) statusNode.textContent = 'No se encontró la dirección en el mapa abierto. Usa "Mostrar en Google Maps", haz clic en el punto exacto, copia las coordenadas y pégalas en el campo de abajo.';
            return;
        }

        const firstMatch = results[0];
        if (fields.lat) fields.lat.value = Number(firstMatch.lat).toFixed(8);
        if (fields.lng) fields.lng.value = Number(firstMatch.lon).toFixed(8);
        updateMapsButton(root);
        if (statusNode) statusNode.textContent = '✓ Coordenadas importadas. Recuerda guardar el formulario.';
        flashCoordFields(fields);
    } catch {
        if (statusNode) statusNode.textContent = 'No se pudo conectar con el servicio de geocodificación. Usa "Mostrar en Google Maps" y el campo de pegado de abajo.';
    } finally {
        geocodeBtn.disabled = false;
    }
});

// ── Delegated: click on import button ────────────────────────────────────────
document.addEventListener('click', (event) => {
    const importBtn = event.target.closest('.address-editor-import-btn');
    if (!importBtn) {
        return;
    }

    const root = importBtn.closest('[data-address-editor]');
    if (!root) {
        return;
    }

    const mapsSourceField = root.querySelector('.address-editor-maps-source');
    const statusNode      = root.querySelector('.address-editor-status');
    const parsedCoordinates = parseCoordinatesFromText(mapsSourceField?.value ?? '');

    if (!parsedCoordinates) {
        if (statusNode) statusNode.textContent = 'No pude extraer coordenadas válidas. Pega un link de Google Maps que apunte al lugar correcto o unas coordenadas en formato lat,lng.';
        return;
    }

    const fields = getFields(root);
    if (fields.lat) fields.lat.value = parsedCoordinates.lat;
    if (fields.lng) fields.lng.value = parsedCoordinates.lng;
    updateMapsButton(root);
    if (statusNode) statusNode.textContent = '✓ Coordenadas importadas. Recuerda guardar el formulario.';
    flashCoordFields(fields);
});

// ── Initial maps-button state for editors already in the DOM ─────────────────
const initAllInRoot = (root) => {
    if (root.matches?.('[data-address-editor]')) {
        updateMapsButton(root);
        return;
    }
    root.querySelectorAll?.('[data-address-editor]').forEach(updateMapsButton);
};

document.addEventListener('DOMContentLoaded', () => initAllInRoot(document));

export const EstetiCANAddressEditor = {
    init(root = document) {
        initAllInRoot(root);
    },
};

if (!window.EstetiCANAddressEditor) {
    window.EstetiCANAddressEditor = EstetiCANAddressEditor;
}
