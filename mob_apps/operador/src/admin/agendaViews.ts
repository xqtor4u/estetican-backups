export type CalView = 'day' | 'week' | 'month';

export function toDateStr(d: Date) {
  return d.toISOString().slice(0, 10);
}

export function addDays(d: Date, n: number) {
  const r = new Date(d);
  r.setDate(r.getDate() + n);
  return r;
}

export function fmtDate(d: Date) {
  return d.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' });
}

function startOfWeekMonday(d: Date): Date {
  const day = d.getDay(); // 0=domingo..6=sábado
  const diff = (day === 0 ? -6 : 1) - day;
  return addDays(d, diff);
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

export function groupByDate<T extends { date: string }>(items: T[]): { date: string; items: T[] }[] {
  const map = new Map<string, T[]>();
  for (const item of items) {
    if (!map.has(item.date)) map.set(item.date, []);
    map.get(item.date)!.push(item);
  }
  return Array.from(map.entries())
    .sort((a, b) => a[0].localeCompare(b[0]))
    .map(([date, dayItems]) => ({ date, items: dayItems }));
}

export function dayHeaderLabel(dateStr: string, today: Date): string {
  const d = new Date(dateStr + 'T12:00:00');
  if (toDateStr(d) === toDateStr(today)) return 'Hoy';
  if (toDateStr(d) === toDateStr(addDays(today, 1))) return 'Mañana';
  const label = d.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
  return label.charAt(0).toUpperCase() + label.slice(1);
}
