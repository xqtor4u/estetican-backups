import React, { useEffect, useRef, useState } from 'react';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { readExifDate } from './lib/exifDate';

interface PhotoEditorModalProps {
  file: File;
  /** Nombre que aparece en la marca de agua (del usuario o de la mascota) */
  label: string;
  /** Si es false, no se dibuja marca de agua aunque el archivo la soporte (regla de negocio) */
  watermarkEnabled: boolean;
  onCancel: () => void;
  onConfirm: (blob: Blob) => void;
}

function drawWatermark(source: HTMLCanvasElement, label: string, date: Date | null): HTMLCanvasElement {
  const out = document.createElement('canvas');
  out.width = source.width;
  out.height = source.height;
  const ctx = out.getContext('2d');
  if (!ctx) return source;
  ctx.drawImage(source, 0, 0);

  const dateLabel = (date ?? new Date()).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
  const text = `${label} · ${dateLabel}`;
  const fontSize = Math.max(12, Math.round(out.width * 0.035));
  const barHeight = Math.round(fontSize * 2.2);

  ctx.fillStyle = 'rgba(0,0,0,0.55)';
  ctx.fillRect(0, out.height - barHeight, out.width, barHeight);

  ctx.fillStyle = '#ffffff';
  ctx.font = `${fontSize}px sans-serif`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(text, out.width / 2, out.height - barHeight / 2, out.width - 16);

  return out;
}

export function PhotoEditorModal({ file, label, watermarkEnabled, onCancel, onConfirm }: PhotoEditorModalProps) {
  const imgRef = useRef<HTMLImageElement>(null);
  const cropperRef = useRef<Cropper | null>(null);
  const [imgSrc] = useState(() => URL.createObjectURL(file));
  const [exifDate, setExifDate] = useState<Date | null>(null);
  const [processing, setProcessing] = useState(false);

  useEffect(() => {
    readExifDate(file).then(setExifDate).catch(() => setExifDate(null));
  }, [file]);

  useEffect(() => {
    if (!imgRef.current) return;
    const cropper = new Cropper(imgRef.current, {
      aspectRatio: 1,
      viewMode: 1,
      dragMode: 'move',
      autoCropArea: 1,
      background: false,
      guides: false,
    });
    cropperRef.current = cropper;
    return () => {
      cropper.destroy();
      cropperRef.current = null;
      URL.revokeObjectURL(imgSrc);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const rotate = () => cropperRef.current?.rotate(90);

  const confirm = () => {
    const cropper = cropperRef.current;
    if (!cropper) return;
    setProcessing(true);
    const canvas = cropper.getCroppedCanvas({ width: 800, height: 800, imageSmoothingQuality: 'high', fillColor: '#fff' });
    const finalCanvas = watermarkEnabled ? drawWatermark(canvas, label, exifDate) : canvas;
    finalCanvas.toBlob(blob => {
      setProcessing(false);
      if (blob) onConfirm(blob);
    }, 'image/jpeg', 0.9);
  };

  return (
    <div className="fixed inset-0 z-50 bg-black flex flex-col">
      <div className="flex items-center justify-between px-4 py-3 text-white shrink-0">
        <button onClick={onCancel} className="text-sm font-medium px-2 py-1">Cancelar</button>
        <p className="text-sm font-semibold">Editar foto</p>
        <button onClick={rotate} className="flex items-center justify-center p-1.5" aria-label="Rotar">
          <span className="material-symbols-outlined text-2xl">rotate_90_degrees_ccw</span>
        </button>
      </div>

      <div className="flex-1 min-h-0 circular-crop">
        <img ref={imgRef} src={imgSrc} alt="" className="max-w-full block" />
      </div>

      <div className="px-4 py-4 shrink-0">
        <button
          onClick={confirm}
          disabled={processing}
          className="w-full bg-primary text-on-primary font-label-md text-label-md px-4 py-3 rounded-xl disabled:opacity-50"
        >
          {processing ? 'Procesando…' : 'Usar esta foto'}
        </button>
      </div>

      <style>{`
        .circular-crop .cropper-view-box,
        .circular-crop .cropper-face {
          border-radius: 50%;
        }
      `}</style>
    </div>
  );
}
