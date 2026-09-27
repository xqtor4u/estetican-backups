import React, { useRef, useState } from 'react';

export interface SlotGridRange { start: number; end: number }

/** Cómo pintar una celda. `disabled` bloquea el toque igual que `isBusy`. */
export interface SlotAppearance { className: string; title?: string; disabled: boolean }

interface DraggableSlotGridProps {
  businessHours: SlotGridRange;
  /** Tamaño de celda de la cuadrícula, en minutos — sin tocar (30), igual que
   *  `ALL_SLOTS`/`STEP` en `MobCitaNueva` hoy. Cambiar esto cambia cuántas celdas se ven de
   *  un jalón (una cuadrícula de 5 min tendría 6x más celdas — mucho scroll para "ver el
   *  día de un vistazo", que es justo lo que la cuadrícula actual hace bien). La precisión
   *  fina no viene de achicar la celda, sino del arrastre (`snapMinutes`) dentro de ella. */
  cellMinutes?: number;
  /** Granularidad del arrastre para EXTENDER la duración — la celda sigue siendo de 30 min,
   *  pero el arrastre calcula la posición fraccional del dedo dentro de la celda destino, así
   *  que puede ajustar más fino que el tamaño de la celda. Tomas: "debería ser de 5 minutos,
   *  o 10 si no cabe" — se deja como prop justo por eso: si 5 se siente impreciso en un
   *  teléfono real (Fase 3/pasada visual), cambiar a 10 es una sola línea, no una reescritura. */
  snapMinutes?: number;
  minDurationMinutes?: number;
  /** Ocupado por OTRA cita/bloqueo del operador (o por otra línea de esta misma cita con el
   *  mismo operador) — bloquea el toque, mismo criterio que `hardBlocked` en `MobCitaNueva`
   *  hoy. Predicado en vez de una lista de rangos porque el llamador ya tiene esta lógica
   *  calculada por slot (choque exacto vs. solape parcial se resuelven distinto) — pedirle
   *  que la convierta a rangos solo para que este componente la vuelva a descomponer sería
   *  trabajo de ida y vuelta sin necesidad. */
  isBusy: (slotMin: number, slotEnd: number) => boolean;
  /** Ocupado por la JAULA elegida — no bloquea (spec §0.9: en v1 la ventana de la jaula
   *  siempre es la del servicio, así que esto es solo aviso, no una segunda selección
   *  independiente) — se marca con un punto morado en la celda, distinto del violeta que ya
   *  usa `MobCitaNueva` para "otro servicio de esta cita" (significado distinto, color
   *  distinto a propósito). */
  isCageBusy?: (slotMin: number, slotEnd: number) => boolean;
  /** Pintado completo de cada celda (bloque usado a medias, solape parcial, bloqueado, otro
   *  servicio de esta cita, no cabe la duración — SYNC-064/066/067). Si no viene, se usa el
   *  pintado mínimo Inicio/Duración/Ocupado. `isStart`/`inSpan` salen de `value`, para que el
   *  llamador no tenga que recalcularlos. */
  slotAppearance?: (slot: { slotMin: number; slotEnd: number; isStart: boolean; inSpan: boolean }) => SlotAppearance;
  /** `null` = todavía sin colocar (como `line0Placed === false` hoy). */
  value: SlotGridRange | null;
  onChange: (range: SlotGridRange) => void;
}

const DRAG_THRESHOLD_PX = 6;

function buildSlots(hours: SlotGridRange, cellMinutes: number): number[] {
  const slots: number[] = [];
  for (let m = hours.start; m < hours.end; m += cellMinutes) slots.push(m);
  return slots;
}

function minutesToClock(mins: number): string {
  const h = Math.floor(mins / 60), m = mins % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/** Prototipo (ZEUS-043, idea de Tomas 13/09/2026): en vez de un popup landscape con dos
 *  barras de tiempo, enriquecer la cuadrícula tocable que YA existe en `MobCitaNueva` — un
 *  toque sigue fijando la hora de inicio (comportamiento de hoy, sin cambios); mantener el
 *  dedo y arrastrar extiende la duración, con feedback en vivo, sin salir de portrait ni
 *  girar el teléfono. La jaula se resuelve con un aviso visual en la misma cuadrícula, no con
 *  una segunda barra independiente.
 *
 *  Se construye aparte (no reemplaza todavía el grid real de `MobCitaNueva`) para poder
 *  comparar contra el enfoque de `TimelineBar`/`HorarioLandscapePopup` (Fase 2, ya
 *  comiteado) antes de decidir cuál se queda — ninguno de los dos se borra todavía. */
export function DraggableSlotGrid({
  businessHours,
  cellMinutes = 30,
  snapMinutes = 5,
  minDurationMinutes = 15,
  isBusy,
  isCageBusy,
  slotAppearance,
  value,
  onChange,
}: DraggableSlotGridProps) {
  const slots = buildSlots(businessHours, cellMinutes);
  const gridRef = useRef<HTMLDivElement>(null);
  const dragRef = useRef<{
    startSlotMin: number;
    startClientX: number;
    startClientY: number;
    dragging: boolean;
  } | null>(null);

  // Solo para forzar un re-render mientras se arrastra fuera de React state normal no hace
  // falta — `onChange` ya viene del padre y dispara su propio re-render con el `value` nuevo.
  const [, forceTick] = useState(0);

  const cellAtPoint = (clientX: number, clientY: number): { slotMin: number; rect: DOMRect } | null => {
    const el = document.elementFromPoint(clientX, clientY);
    const cellEl = el?.closest<HTMLElement>('[data-slot-min]');
    if (!cellEl) return null;
    return { slotMin: Number(cellEl.dataset.slotMin), rect: cellEl.getBoundingClientRect() };
  };

  const handlePointerDown = (e: React.PointerEvent<HTMLButtonElement>, slotMin: number) => {
    const slotEnd = slotMin + cellMinutes;
    if (isBusy(slotMin, slotEnd)) return;
    dragRef.current = { startSlotMin: slotMin, startClientX: e.clientX, startClientY: e.clientY, dragging: false };
    gridRef.current?.setPointerCapture(e.pointerId);
  };

  const handlePointerMove = (e: React.PointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current;
    if (!drag) return;

    const dx = e.clientX - drag.startClientX;
    const dy = e.clientY - drag.startClientY;
    if (!drag.dragging && Math.hypot(dx, dy) < DRAG_THRESHOLD_PX) return; // todavía podría ser un tap
    drag.dragging = true;

    const target = cellAtPoint(e.clientX, e.clientY);
    if (!target) return;

    // Posición fraccional del dedo dentro de la celda destino (0..1) — de acá sale la
    // precisión más fina que el tamaño de celda (comentario de cabecera del componente).
    const fraction = Math.min(1, Math.max(0, (e.clientX - target.rect.left) / target.rect.width));
    const rawMinutes = (target.slotMin - drag.startSlotMin) + fraction * cellMinutes;
    const snapped = Math.round(rawMinutes / snapMinutes) * snapMinutes;

    const start = drag.startSlotMin;
    let end = start + Math.max(minDurationMinutes, snapped);
    end = Math.min(businessHours.end, end);

    onChange({ start, end });
  };

  const handlePointerUp = (e: React.PointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current;
    if (drag && !drag.dragging) {
      // Toque simple, sin arrastre real — fija el inicio y conserva la duración actual (o la
      // mínima si todavía no había nada), igual que `setLineStart` hoy.
      const duration = value ? value.end - value.start : minDurationMinutes;
      const start = drag.startSlotMin;
      const end = Math.min(businessHours.end, start + duration);
      onChange({ start, end });
    }
    dragRef.current = null;
    try { gridRef.current?.releasePointerCapture(e.pointerId); } catch { /* ya liberado */ }
    forceTick(t => t + 1);
  };

  return (
    <div
      ref={gridRef}
      onPointerMove={handlePointerMove}
      onPointerUp={handlePointerUp}
      onPointerCancel={handlePointerUp}
      className="grid grid-cols-4 gap-2"
      /* `touch-action` se fija UNA sola vez al iniciar cada gesto táctil — no se puede
       *  alternar a 'none' recién cuando `dragging` se vuelve true (para entonces el
       *  navegador ya decidió). `pan-y` dejamos el scroll vertical de la página disponible
       *  para gestos claramente verticales (arreglo del bug real: la cuadrícula entera
       *  bloqueaba el scroll de `MobCitaNueva`, `min-h-screen` sin contenedor propio) y
       *  capturamos el resto vía Pointer Events — funciona bien acá porque extender
       *  duración casi siempre es un arrastre horizontal (4 columnas = 2h por fila; solo un
       *  arrastre puramente vertical, más de 2h de una sola pasada recta hacia abajo, podría
       *  perderse contra el scroll nativo — caso raro, no el reportado). */
      style={{ touchAction: 'pan-y' }}
    >
      {slots.map(slotMin => {
        const slotEnd = slotMin + cellMinutes;
        const occ = isBusy(slotMin, slotEnd);
        const cageBusy = isCageBusy?.(slotMin, slotEnd) ?? false;
        const isStart = value != null && slotMin === value.start;
        const inSpan = value != null && slotMin >= value.start && slotMin < value.end && !isStart;
        const look: SlotAppearance = slotAppearance?.({ slotMin, slotEnd, isStart, inSpan }) ?? {
          disabled: occ,
          className: occ
            ? 'bg-error-container text-on-error-container border border-error/20 cursor-not-allowed line-through'
            : isStart
              ? 'bg-primary text-on-primary shadow-md scale-105'
              : inSpan
                ? 'bg-primary/25 text-primary border border-primary/30'
                : 'bg-surface-container text-on-surface border border-outline-variant active:scale-95 hover:border-primary/40',
        };

        return (
          <button
            key={slotMin}
            type="button"
            data-slot-min={slotMin}
            disabled={look.disabled}
            title={look.title}
            onPointerDown={e => handlePointerDown(e, slotMin)}
            style={{ position: 'relative' }}
            className={`py-2.5 rounded-xl text-sm font-mono font-semibold transition-all select-none ${look.className}`}
          >
            {minutesToClock(slotMin)}
            {cageBusy && (
              <span
                aria-label="Jaula ocupada en este horario"
                style={{ position: 'absolute', top: 3, right: 3, width: 6, height: 6, borderRadius: 9999, background: '#6f42c1' }}
              />
            )}
          </button>
        );
      })}
    </div>
  );
}
