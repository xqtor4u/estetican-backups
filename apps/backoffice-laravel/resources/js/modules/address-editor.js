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

const initSingleEditor = (root) => {
    if (!root || root.dataset.addressEditorInitialized === 'true') {
        return;
    }

    root.dataset.addressEditorInitialized = 'true';

    const fields = {
        street: root.querySelector('.address-editor-street'),
        exterior: root.querySelector('.address-editor-exterior'),
        interior: root.querySelector('.address-editor-interior'),
        colonia: root.querySelector('.address-editor-colonia'),
        city: root.querySelector('.address-editor-city'),
        state: root.querySelector('.address-editor-state'),
        zip: root.querySelector('.address-editor-zip'),
        country: root.querySelector('.address-editor-country'),
        lat: root.querySelector('.address-editor-lat'),
        lng: root.querySelector('.address-editor-lng'),
    };

    const geocodeButton = root.querySelector('.address-editor-geocode-btn');
    const mapsButton = root.querySelector('.address-editor-maps-btn');
    const importButton = root.querySelector('.address-editor-import-btn');
    const statusNode = root.querySelector('.address-editor-status');
    const mapsSourceField = root.querySelector('.address-editor-maps-source');

    if (!geocodeButton || !mapsButton || !importButton || !statusNode || !mapsSourceField) {
        return;
    }

    const buildAddress = () => {
        const streetLine = [fields.street?.value, fields.exterior?.value]
            .map((fieldValue) => (fieldValue || '').trim())
            .filter((fieldValue) => fieldValue !== '')
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
            .map((fieldValue) => (fieldValue || '').trim())
            .filter((fieldValue) => fieldValue !== '')
            .join(', ');
    };

    const hasCoordinates = () => (fields.lat?.value || '').trim() !== '' && (fields.lng?.value || '').trim() !== '';
    const hasMinimumAddress = () => (fields.street?.value || '').trim() !== ''
        && (fields.exterior?.value || '').trim() !== ''
        && (fields.city?.value || '').trim() !== '';

    const updateMapsButton = () => {
        const address = buildAddress();

        if (hasCoordinates()) {
            mapsButton.href = 'https://www.google.com/maps?q=' + encodeURIComponent(`${fields.lat.value.trim()},${fields.lng.value.trim()}`);
            mapsButton.classList.remove('disabled');
            mapsButton.setAttribute('aria-disabled', 'false');
            statusNode.textContent = '';
            return;
        }

        if (!hasMinimumAddress()) {
            mapsButton.href = '#';
            mapsButton.classList.add('disabled');
            mapsButton.setAttribute('aria-disabled', 'true');
            statusNode.textContent = 'Captura al menos calle, número exterior y ciudad para buscar la dirección con precisión.';
            return;
        }

        mapsButton.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address);
        mapsButton.classList.remove('disabled');
        mapsButton.setAttribute('aria-disabled', 'false');
        statusNode.textContent = 'Google Maps abrirá una búsqueda con la dirección capturada para que puedas validar la ubicación.';
    };

    mapsButton.addEventListener('click', (event) => {
        if (mapsButton.classList.contains('disabled')) {
            event.preventDefault();
        }
    });

    [fields.street, fields.exterior, fields.interior, fields.colonia, fields.city, fields.state, fields.zip, fields.country, fields.lat, fields.lng]
        .filter(Boolean)
        .forEach((field) => field.addEventListener('input', updateMapsButton));

    const checkCoordinateDecimals = (event) => {
        const field = event.target;
        const val = (field.value || '').trim();
        if (!val) return;

        const parts = val.split('.');
        if (parts.length === 2 && parts[1].length > 8) {
            if (confirm('Google proporciona coordenadas con demasiados decimales y el sistema no permite guardarlos tan largos. ¿Deseas que los ajustemos automáticamente al límite permitido?')) {
                field.value = Number(val).toFixed(8);
                updateMapsButton();
            }
        }
    };

    const handleCoordinatePaste = (event) => {
        const pastedText = (event.clipboardData || window.clipboardData).getData('text');
        const parsed = parseCoordinatesFromText(pastedText);
        
        if (parsed) {
            event.preventDefault(); // Detener el pegado crudo
            
            fields.lat.value = parsed.lat;
            fields.lng.value = parsed.lng;
            
            if (statusNode) {
                statusNode.textContent = 'Coordenadas detectadas en el portapapeles. Se dividieron y recortaron automáticamente.';
            }
            
            updateMapsButton();
        }
    };

    if (fields.lat) {
        fields.lat.addEventListener('change', checkCoordinateDecimals);
        fields.lat.addEventListener('paste', handleCoordinatePaste);
    }
    
    if (fields.lng) {
        fields.lng.addEventListener('change', checkCoordinateDecimals);
        fields.lng.addEventListener('paste', handleCoordinatePaste);
    }

    geocodeButton.addEventListener('click', async () => {
        const address = buildAddress();

        if (!hasMinimumAddress()) {
            statusNode.textContent = 'Captura al menos calle, número exterior y ciudad antes de intentar traer coordenadas.';
            return;
        }

        geocodeButton.disabled = true;
        statusNode.textContent = 'Buscando coordenadas aproximadas...';

        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(address)}`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('No se pudo consultar el servicio de geocodificación.');
            }

            const results = await response.json();

            if (!Array.isArray(results) || results.length === 0) {
                statusNode.textContent = 'No se encontraron coordenadas. Revisa la dirección o confirma la búsqueda en Google Maps.';
                return;
            }

            const firstMatch = results[0];
            fields.lat.value = Number(firstMatch.lat).toFixed(8);
            fields.lng.value = Number(firstMatch.lon).toFixed(8);
            updateMapsButton();
            statusNode.textContent = 'Coordenadas importadas. Verifica en Google Maps que el punto sea correcto.';
        } catch {
            statusNode.textContent = 'No se pudieron importar coordenadas automáticamente. Usa Google Maps para validar o captura latitud/longitud manualmente.';
        } finally {
            geocodeButton.disabled = false;
        }
    });

    importButton.addEventListener('click', () => {
        const parsedCoordinates = parseCoordinatesFromText(mapsSourceField.value);

        if (!parsedCoordinates) {
            statusNode.textContent = 'No pude extraer coordenadas válidas. Pega un link de Google Maps que apunte al lugar correcto o unas coordenadas en formato lat,lng.';
            return;
        }

        fields.lat.value = parsedCoordinates.lat;
        fields.lng.value = parsedCoordinates.lng;
        updateMapsButton();
        statusNode.textContent = 'Coordenadas exactas importadas desde Google Maps. Ya puedes guardar.';
    });

    updateMapsButton();
};

export const EstetiCANAddressEditor = {
    init(root = document) {
        if (root.matches?.('[data-address-editor]')) {
            initSingleEditor(root);
            return;
        }

        root.querySelectorAll?.('[data-address-editor]').forEach(initSingleEditor);
    },
};

if (!window.EstetiCANAddressEditor) {
    window.EstetiCANAddressEditor = EstetiCANAddressEditor;
}

document.addEventListener('DOMContentLoaded', () => {
    window.EstetiCANAddressEditor?.init(document);
});