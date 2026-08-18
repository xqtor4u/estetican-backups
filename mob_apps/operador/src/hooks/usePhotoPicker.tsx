import React, { useRef } from 'react';

/**
 * Un solo <input type="file" accept="image/*"> sin `capture` debería dejar elegir entre
 * cámara y galería en el selector nativo — pero en la práctica varios navegadores/WebView
 * de Android solo ofrecen la galería ahí. `capture="environment"` fuerza la cámara directo,
 * así que hacen falta dos inputs separados, cada uno disparado por código (`ref.click()`)
 * desde quien sea que decida la fuente (un botón visible, o un menú como `PhotoSourceSheet`).
 */
export function usePhotoPicker(onPick: (file: File) => void) {
  const cameraRef = useRef<HTMLInputElement>(null);
  const galleryRef = useRef<HTMLInputElement>(null);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (f) onPick(f);
  };

  const inputs = (
    <>
      <input ref={cameraRef} type="file" accept="image/*" capture="environment" className="hidden" onChange={handleChange} />
      <input ref={galleryRef} type="file" accept="image/*" className="hidden" onChange={handleChange} />
    </>
  );

  return {
    inputs,
    openCamera: () => cameraRef.current?.click(),
    openGallery: () => galleryRef.current?.click(),
  };
}
