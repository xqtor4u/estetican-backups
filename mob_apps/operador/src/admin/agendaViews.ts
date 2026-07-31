export type CalView = 'day' | 'week' | 'month';

/**
 * Fecha local en formato YYYY-MM-DD. No usar `toISOString()` aquí: convierte a UTC,
 * y un `Date` que no esté exactamente en medianoche local puede cruzar el límite de
 * día según la hora del dispositivo y su huso horario, desalineando la fecha en un día.
 */
export function toDateStr(d: Date) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

export function addDays(d: Date, n: number) {
  const r = new Date(d);
  r.setDate(r.getDate() + n);
  return r;
}

export function fmtDate(d: Date) {
  return d.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' });
}

export function startOfWeekMonday(d: Date): Date {
  const day = d.getDay(); // 0=domingo..6=sábado
  const diff = (day === 0 ? -6 : 1) - day;
  return addDays(d, diff);
}

/** Los 7 días (lunes a domingo) de la semana que contiene `anchor`. */
export function weekDays(anchor: Date): Date[] {
  const start = startOfWeekMonday(anchor);
  return Array.from({ length: 7 }, (_, i) => addDays(start, i));
}

/** Grid completo de semanas (lunes a domingo) que cubre el mes de `anchor`, incluyendo días de meses vecinos para completar la última semana. */
export function monthGridDays(anchor: Date): { date: Date; outside: boolean }[] {
  const month = anchor.getMonth();
  const firstOfMonth = new Date(anchor.getFullYear(), month, 1);
  const lastOfMonth = new Date(anchor.getFullYear(), month + 1, 0);
  const start = startOfWeekMonday(firstOfMonth);
  const end = addDays(startOfWeekMonday(lastOfMonth), 6);

  const days: { date: Date; outside: boolean }[] = [];
  for (let cur = start; cur <= end; cur = addDays(cur, 1)) {
    days.push({ date: cur, outside: cur.getMonth() !== month });
  }
  return days;
}

export function rangeForView(calView: CalView, anchor: Date): { start: Date; end: Date } {
  if (calView === 'week') {
    const start = startOfWeekMonday(anchor);
    return { start, end: addDays(start, 6) };
  }
  if (calView === 'month') {
    const start = new Date(anchor.getFullYear(), anchor.getMonth(), 1);
    const end = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0);
    return { start, end };
  }
  return { start: anchor, end: anchor };
}

/** Mueve la fecha ancla un paso hacia adelante (dir=1) o atrás (dir=-1) según la vista activa. */
export function shiftAnchor(calView: CalView, anchor: Date, dir: 1 | -1): Date {
  if (calView === 'week') return addDays(anchor, 7 * dir);
  if (calView === 'month') {
    const d = new Date(anchor);
    d.setDate(1); // evita desbordes de día al cambiar a meses más cortos
    d.setMonth(d.getMonth() + dir);
    return d;
  }
  return addDays(anchor, dir);
}

export function rangeLabel(calView: CalView, anchor: Date): string {
  const { start, end } = rangeForView(calView, anchor);
  if (calView === 'week') {
    return `Semana del ${start.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })} al ${end.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })}`;
  }
  if (calView === 'month') {
    const label = anchor.toLocaleDateString('es-MX', { month: 'long', year: 'numeric' });
    return label.charAt(0).toUpperCase() + label.slice(1);
  }
  return '';
}

/** Agrupa por fecha (`YYYY-MM-DD`) para lookup O(1) por día, usado por los grids de semana/mes. */
export function groupByDateMap<T extends { date: string }>(items: T[]): Map<string, T[]> {
  const map = new Map<string, T[]>();
  for (const item of items) {
    if (!map.has(item.date)) map.set(item.date, []);
    map.get(item.date)!.push(item);
  }
  return map;
}

/** Expande un rango datetime ("YYYY-MM-DD HH:MM:SS") a la lista de fechas ("YYYY-MM-DD") que toca — usado para marcar bloqueos de disponibilidad que abarcan varios días en los grids de semana/mes. */
export function expandDateRange(startsAt: string, endsAt: string): string[] {
  const start = new Date(startsAt.replace(' ', 'T'));
  const end = new Date(endsAt.replace(' ', 'T'));
  const dates: string[] = [];
  const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());
  const stop = new Date(end.getFullYear(), end.getMonth(), end.getDate());
  while (cursor <= stop) {
    dates.push(toDateStr(cursor));
    cursor.setDate(cursor.getDate() + 1);
  }
  return dates;
}
