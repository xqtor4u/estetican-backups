import { useMemo, useState } from 'react';

type Dir = 'asc' | 'desc';

export function useSortable<T>(data: T[], defaultKey = '', defaultDir: Dir = 'asc') {
  const [sortKey, setSortKey]     = useState(defaultKey);
  const [direction, setDirection] = useState<Dir>(defaultDir);

  function toggle(k: string) {
    if (sortKey === k) setDirection(d => (d === 'asc' ? 'desc' : 'asc'));
    else { setSortKey(k); setDirection('asc'); }
  }

  const sorted = useMemo(() => {
    if (!sortKey) return data;
    return [...data].sort((a, b) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const va: unknown = (a as any)[sortKey];
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const vb: unknown = (b as any)[sortKey];
      const cmp =
        typeof va === 'number' && typeof vb === 'number'
          ? va - vb
          : String(va ?? '').localeCompare(String(vb ?? ''), 'es-MX', { numeric: true });
      return direction === 'asc' ? cmp : -cmp;
    });
  }, [data, sortKey, direction]);

  return { sortKey, direction, toggle, sorted };
}

/** Botón de encabezado sorteable — usar en la fila de headers de cualquier lista */
export function SortBtn({
  label, col, sortKey, direction, onToggle, className = '',
}: {
  label: string;
  col: string;
  sortKey: string;
  direction: Dir;
  onToggle: (k: string) => void;
  className?: string;
}) {
  const active = sortKey === col;
  const icon   = active
    ? (direction === 'asc' ? 'arrow_upward' : 'arrow_downward')
    : 'unfold_more';
  return (
    <button
      onClick={() => onToggle(col)}
      className={`flex items-center gap-0.5 text-xs font-bold uppercase tracking-wide transition-colors ${
        active ? 'text-primary' : 'text-on-surface-variant'
      } ${className}`}
    >
      {label}
      <span
        className="material-symbols-outlined"
        style={{ fontSize: 14, opacity: active ? 1 : 0.4 }}
      >
        {icon}
      </span>
    </button>
  );
}
