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

const updatePreviewCopy = (form, state, originalState) => {
    const summaryLabel = document.querySelector('[data-active-palette-label]');
    const summaryDensity = document.querySelector('[data-active-density-label]');
    const statusNode = form.querySelector('[data-palette-preview-status]');
    const isDirty = state.palette !== originalState.palette || state.density !== originalState.density;

    if (summaryLabel && state.paletteLabel) {
        summaryLabel.textContent = state.paletteLabel;
    }

    if (summaryDensity) {
        summaryDensity.textContent = state.density === 'compact' ? 'compacta' : 'cómoda';
    }

    if (statusNode) {
        statusNode.textContent = isDirty
            ? `Vista previa activa: ${state.paletteLabel} · densidad ${state.density === 'compact' ? 'compacta' : 'cómoda'}. Se guarda al enviar esta sección.`
            : 'Vista previa en vivo. Solo se guarda cuando envías esta sección.';
    }
};

const applyVisualizationPreview = (state, form, originalState) => {
    document.body.dataset.themePalette = state.palette;
    setBodyDensity(state.density);
    updatePreviewCopy(form, state, originalState);
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

    updatePreviewCopy(form, getState(), originalState);

    form.addEventListener('change', (event) => {
        const paletteInput = event.target.closest(PALETTE_INPUT_SELECTOR);
        const isDensityChange = event.target.matches(DENSITY_SELECT_SELECTOR);

        if (!paletteInput && !isDensityChange) {
            return;
        }

        updateActiveCards(inputs);
        applyVisualizationPreview(getState(), form, originalState);
    });

    form.addEventListener('reset', () => {
        window.requestAnimationFrame(() => {
            updateActiveCards(inputs);
            applyVisualizationPreview(originalState, form, originalState);
        });
    });
};

document.addEventListener('DOMContentLoaded', initPalettePreview);