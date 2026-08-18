import React from 'react';

interface PhotoSourceSheetProps {
  title?: string;
  onPickCamera: () => void;
  onPickGallery: () => void;
  onClose: () => void;
}

/**
 * Reusa el mismo patrón de overlay que `PhotoEditorModal` (`fixed inset-0 z-50`, renderizado
 * como hermano de nivel superior en el árbol, nunca anidado dentro de un elemento con
 * `transform`) — probado en producción real. Un intento anterior de esta misma idea
 * (hoja "Tomar foto"/"Elegir de galería") falló en un dispositivo real (solo se veía el fondo
 * oscurecido, sin los botones) y se revirtió sin determinar la causa raíz — ver BITACORA
 * 06/08/2026. Este componente evita esa clase de bug reutilizando la estructura que sí
 * funciona, en vez de reinventar el overlay.
 */
export function PhotoSourceSheet({ title = 'Cambiar foto', onPickCamera, onPickGallery, onClose }: PhotoSourceSheetProps) {
  return (
    <div className="fixed inset-0 z-50 bg-black/60 flex items-end" onClick={onClose}>
      <div
        className="w-full bg-surface rounded-t-3xl p-4 pb-8 flex flex-col gap-2"
        onClick={e => e.stopPropagation()}
      >
        <p className="text-center text-sm font-semibold text-on-surface-variant mb-1">{title}</p>
        <button
          onClick={onPickCamera}
          className="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-surface-container active:bg-surface-container-high text-on-surface font-medium transition-colors"
        >
          <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>photo_camera</span>
          Tomar foto
        </button>
        <button
          onClick={onPickGallery}
          className="flex items-center gap-3 px-4 py-3.5 rounded-2xl bg-surface-container active:bg-surface-container-high text-on-surface font-medium transition-colors"
        >
          <span className="material-symbols-outlined text-primary" style={{ fontVariationSettings: "'FILL' 1" }}>image</span>
          Elegir de la galería
        </button>
        <button
          onClick={onClose}
          className="flex items-center justify-center px-4 py-3.5 rounded-2xl border border-outline-variant text-on-surface-variant font-medium mt-1"
        >
          Cancelar
        </button>
      </div>
    </div>
  );
}
