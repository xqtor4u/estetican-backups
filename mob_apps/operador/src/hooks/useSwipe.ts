import { useRef } from 'react';
import type { TouchEvent } from 'react';

interface SwipeOpts {
  /** Dedo derecha → izquierda (contenido "avanza") → siguiente */
  onSwipeLeft?: () => void;
  /** Dedo izquierda → derecha → anterior */
  onSwipeRight?: () => void;
  /** Desplazamiento horizontal mínimo para contar como swipe (px) */
  threshold?: number;
  /** Desplazamiento vertical máximo tolerado (px) — más que esto = scroll, no swipe */
  maxVertical?: number;
}

/**
 * Swipe horizontal para las pantallas de detalle (ir a la mascota / cliente / cita
 * anterior o siguiente). Handlers para poner en el contenedor raíz de la pantalla.
 * Un elemento con `data-noswipe` (o dentro de uno) NO dispara el gesto — usarlo en
 * tiras/carruseles que scrollean en horizontal.
 */
export function useSwipe({ onSwipeLeft, onSwipeRight, threshold = 90, maxVertical = 50 }: SwipeOpts) {
  const start = useRef<{ x: number; y: number; t: number } | null>(null);

  const onTouchStart = (e: TouchEvent) => {
    if (e.touches.length !== 1) { start.current = null; return; }
    const target = e.target as HTMLElement;
    if (target.closest?.('[data-noswipe]')) { start.current = null; return; }
    const t = e.touches[0];
    start.current = { x: t.clientX, y: t.clientY, t: Date.now() };
  };

  const onTouchEnd = (e: TouchEvent) => {
    const s = start.current;
    start.current = null;
    if (!s) return;
    const t = e.changedTouches[0];
    const dx = t.clientX - s.x;
    const dy = t.clientY - s.y;
    if (Date.now() - s.t > 800) return;        // demasiado lento
    if (Math.abs(dy) > maxVertical) return;    // fue vertical (scroll)
    if (Math.abs(dx) < threshold) return;      // muy corto
    if (typeof window.getSelection === 'function' && String(window.getSelection())) return; // selección de texto
    if (dx < 0) onSwipeLeft?.();
    else onSwipeRight?.();
  };

  return { onTouchStart, onTouchEnd };
}
