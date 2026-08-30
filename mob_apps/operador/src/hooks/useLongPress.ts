import type { SyntheticEvent } from 'react';

/**
 * No es un hook de React (no usa estado ni efectos) — es una fábrica de handlers de evento,
 * segura de llamar dentro de un `.map()` porque no depende del árbol de hooks, solo de
 * closures frescos por render (mismo patrón que los `onClick={() => ...}` inline ya usados
 * en las listas de esta app). Suprime el `click` que sigue al `touchend`/`mouseup` cuando sí
 * se disparó el long-press, para no navegar/activar el `onClick` del elemento padre.
 */
export function createLongPressHandlers(onLongPress: () => void, delayMs = 500, onShortPress?: () => void) {
  let timer: ReturnType<typeof setTimeout> | null = null;
  let triggered = false;

  const start = () => {
    triggered = false;
    timer = setTimeout(() => {
      triggered = true;
      onLongPress();
    }, delayMs);
  };
  const clear = () => {
    if (timer) { clearTimeout(timer); timer = null; }
  };

  return {
    onTouchStart: start,
    onTouchEnd: clear,
    onTouchMove: clear,
    onTouchCancel: clear,
    onMouseDown: start,
    onMouseUp: clear,
    onMouseLeave: clear,
    onContextMenu: (e: SyntheticEvent) => e.preventDefault(),
    onClick: (e: SyntheticEvent) => {
      if (triggered) {
        e.preventDefault();
        e.stopPropagation();
        triggered = false;
        return;
      }
      onShortPress?.();
    },
  };
}
