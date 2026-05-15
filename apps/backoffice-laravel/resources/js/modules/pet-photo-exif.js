const EXIFR_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/exifr/dist/lite.umd.js';
const DEFAULT_STATUS = 'Si la dejas vacia, el sistema intentara leerla desde los metadatos EXIF de la foto.';

let exifrLoader = null;

const loadExifr = () => {
    if (window.exifr) {
        return Promise.resolve(window.exifr);
    }

    if (exifrLoader) {
        return exifrLoader;
    }

    exifrLoader = new Promise((resolve, reject) => {
        const existingScript = document.querySelector('script[data-exifr-loader="true"]');

        if (existingScript) {
            existingScript.addEventListener('load', () => resolve(window.exifr), { once: true });
            existingScript.addEventListener('error', () => reject(new Error('No se pudo cargar exifr.')), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = EXIFR_SCRIPT_URL;
        script.async = true;
        script.dataset.exifrLoader = 'true';
        script.addEventListener('load', () => resolve(window.exifr), { once: true });
        script.addEventListener('error', () => reject(new Error('No se pudo cargar exifr.')), { once: true });
        document.head.appendChild(script);
    });

    return exifrLoader;
};

const initializeExifPrefill = (fileInput) => {
    if (!fileInput || fileInput.dataset.photoExifInitialized === 'true') {
        return;
    }

    const container = fileInput.closest('.row') || fileInput.closest('form');

    if (!container) {
        return;
    }

    const takenAtInput = container.querySelector('[data-photo-taken-at]');
    const statusElement = container.querySelector('[data-photo-exif-status]');

    if (!takenAtInput || !statusElement) {
        return;
    }

    fileInput.dataset.photoExifInitialized = 'true';

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];

        if (!file) {
            statusElement.textContent = DEFAULT_STATUS;
            return;
        }

        if (takenAtInput.value) {
            statusElement.textContent = 'Ya hay una fecha capturada. No se reemplazo con EXIF.';
            return;
        }

        try {
            const exifr = await loadExifr();
            const metadata = await exifr.parse(file, ['DateTimeOriginal', 'DateTimeDigitized', 'DateTime']);
            const detectedDate = metadata?.DateTimeOriginal || metadata?.DateTimeDigitized || metadata?.DateTime;

            if (!(detectedDate instanceof Date) || Number.isNaN(detectedDate.getTime())) {
                statusElement.textContent = 'No se encontro fecha EXIF en esta imagen. Puedes capturarla manualmente.';
                return;
            }

            const localDate = new Date(detectedDate.getTime() - (detectedDate.getTimezoneOffset() * 60000));
            takenAtInput.value = localDate.toISOString().slice(0, 16);
            statusElement.textContent = 'Fecha detectada desde EXIF y prellenada automaticamente.';
        } catch {
            statusElement.textContent = 'No se pudo leer EXIF en el navegador. Si quieres, captura la fecha manualmente.';
        }
    });
};

const initPetPhotoExif = () => {
    document.querySelectorAll('input[type="file"][name="photo"]').forEach(initializeExifPrefill);
};

document.addEventListener('DOMContentLoaded', initPetPhotoExif);