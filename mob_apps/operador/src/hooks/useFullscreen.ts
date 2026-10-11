import { useState, useEffect, useCallback } from 'react';

// Prefijos webkit: algunos WebView/Chromium viejos solo exponen la API con prefijo.
type FsDocument = Document & {
  webkitFullscreenEnabled?: boolean;
  webkitFullscreenElement?: Element | null;
  webkitExitFullscreen?: () => Promise<void> | void;
};
type FsElement = HTMLElement & { webkitRequestFullscreen?: () => Promise<void> | void };

const doc = document as FsDocument;

function isSupported(): boolean {
  return !!(doc.fullscreenEnabled ?? doc.webkitFullscreenEnabled);
}

function isActive(): boolean {
  return !!(doc.fullscreenElement ?? doc.webkitFullscreenElement);
}

/** Pantalla completa de toda la app (oculta la barra del navegador y la de estado).
 *  `supported` es false donde el navegador/WebView no la permite — ahí no se muestra el botón.
 *  Se sale con el mismo botón o con el gesto/botón "atrás" de Android. */
export function useFullscreen() {
  const [active, setActive] = useState(isActive);

  useEffect(() => {
    const onChange = () => setActive(isActive());
    document.addEventListener('fullscreenchange', onChange);
    document.addEventListener('webkitfullscreenchange', onChange);
    return () => {
      document.removeEventListener('fullscreenchange', onChange);
      document.removeEventListener('webkitfullscreenchange', onChange);
    };
  }, []);

  const toggle = useCallback(async () => {
    try {
      if (isActive()) {
        await (doc.exitFullscreen ? doc.exitFullscreen() : doc.webkitExitFullscreen?.());
      } else {
        const el = document.documentElement as FsElement;
        await (el.requestFullscreen ? el.requestFullscreen({ navigationUI: 'hide' }) : el.webkitRequestFullscreen?.());
      }
    } catch {
      // El navegador rechazó la petición (p. ej. sin gesto del usuario): no hay nada que hacer.
    }
  }, []);

  return { supported: isSupported(), active, toggle };
}
