let initialized = false;

const PALETTE_FORM_SELECTOR = 'form[data-preview-palette-form="true"]';
const PALETTE_INPUT_SELECTOR = 'input[name="ui_color_palette"]';
const DENSITY_SELECT_SELECTOR = 'select[name="ui_density"]';

const setBodyDensity = (density) => {
    document.body.classList.remove('app-density-comfortable', 'app-density-compact');
    document.body.classList.add(`app-density-${density}`);
};

const updateActiveCards = (inputs) => {
    inputs.forEach((input) => {
        const card = input.closest('.settings-palette-card');

        if (!card) {
            return;
        }

        card.classList.toggle('settings-palette-card--active', input.checked);
    });
};

const updatePreviewCopy = (form, state) => {
    const summaryLabel = document.querySelector('[data-active-palette-label]');
    const summaryDensity = document.querySelector('[data-active-density-label]');

    if (summaryLabel && state.paletteLabel) {
        summaryLabel.textContent = state.paletteLabel;
    }

    if (summaryDensity) {
        summaryDensity.textContent = state.density === 'compact' ? 'compacta' : 'cómoda';
    }
};

const showSaveStatus = (form, message, isError = false) => {
    const statusNode = form.querySelector('[data-palette-preview-status]');
    if (!statusNode) return;
    statusNode.textContent = message;
    statusNode.style.color = isError ? 'var(--bs-danger, #dc3545)' : 'var(--app-accent, #c9703c)';
    statusNode.style.fontWeight = '600';
    setTimeout(() => {
        statusNode.style.color = '';
        statusNode.style.fontWeight = '';
        statusNode.textContent = 'Vista previa en vivo. Los cambios se guardan automáticamente.';
    }, 2500);
};

const autoSaveField = async (form, fieldName, value) => {
    const patchUrl = form.action.split('#')[0] + '/field';
    const token = form.querySelector('input[name="_token"]')?.value;

    if (!token) return;

    const body = new FormData();
    body.append('_token', token);
    body.append(fieldName, value);

    try {
        const res = await fetch(patchUrl, { method: 'PATCH', body });
        const data = await res.json().catch(() => null);

        if (res.ok && data?.ok) {
            showSaveStatus(form, 'Guardado ✓');
        } else {
            showSaveStatus(form, 'No se pudo guardar', true);
        }
    } catch {
        showSaveStatus(form, 'Sin conexión — cambio no guardado', true);
    }
};

const applyVisualizationPreview = (state, form) => {
    document.body.dataset.themePalette = state.palette;
    setBodyDensity(state.density);
    updatePreviewCopy(form, state);
};

const initPalettePreview = () => {
    const form = document.querySelector(PALETTE_FORM_SELECTOR);

    if (!form || initialized) {
        return;
    }

    const inputs = Array.from(form.querySelectorAll(PALETTE_INPUT_SELECTOR));
    const densitySelect = form.querySelector(DENSITY_SELECT_SELECTOR);

    if (inputs.length === 0 || !densitySelect) {
        return;
    }

    initialized = true;

    const originalPalette = document.body.dataset.themePalette || '';
    const originalDensity = document.body.classList.contains('app-density-compact') ? 'compact' : 'comfortable';
    const originalInput = inputs.find((input) => input.checked) || inputs[0];
    const originalState = {
        palette: originalPalette,
        paletteLabel: originalInput?.dataset.paletteLabel || '',
        density: originalDensity,
    };

    updateActiveCards(inputs);

    const getState = () => {
        const activeInput = inputs.find((input) => input.checked) || originalInput;

        return {
            palette: activeInput?.value || originalState.palette,
            paletteLabel: activeInput?.dataset.paletteLabel || originalState.paletteLabel,
            density: densitySelect.value || originalState.density,
        };
    };

    updatePreviewCopy(form, getState());

    // Actualizar el texto de estado inicial
    const statusNode = form.querySelector('[data-palette-preview-status]');
    if (statusNode) {
        statusNode.textContent = 'Vista previa en vivo. Los cambios se guardan automáticamente.';
    }

    form.addEventListener('change', (event) => {
        const paletteInput = event.target.closest(PALETTE_INPUT_SELECTOR);
        const isDensityChange = event.target.matches(DENSITY_SELECT_SELECTOR);

        if (!paletteInput && !isDensityChange) {
            return;
        }

        const state = getState();
        updateActiveCards(inputs);
        applyVisualizationPreview(state, form);

        if (paletteInput) {
            autoSaveField(form, 'ui_color_palette', state.palette);
        } else if (isDensityChange) {
            autoSaveField(form, 'ui_density', state.density);
        }
    });

    form.addEventListener('reset', () => {
        window.requestAnimationFrame(() => {
            updateActiveCards(inputs);
            applyVisualizationPreview(originalState, form);
        });
    });
};

document.addEventListener('DOMContentLoaded', initPalettePreview);
