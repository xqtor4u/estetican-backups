import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useSortable, SortBtn } from '../hooks/useSortable';

/* ── Tipos ──────────────────────────────────────────────── */
interface JobBooking {
  id: number;
  fecha: string;
  fecha_iso: string;
  status: string;
  order_folio: string | null;
  services: { name: string; price: number; duration_minutes: number | null }[];
  payments: { id: number; amount: number; payment_method: string; category: string; destination: string; created_at: string }[];
}
interface JobRow {
  key: string;
  id: number;
  fecha_iso: string;
  fecha_short: string;
  status: string;
  has_folio: boolean;
  folio_label: string;
  servicios: string;
  total: number;
}

type StatusFilter = 'pendientes' | 'completadas' | 'canceladas' | 'todas';
type DateRange    = 0 | 30 | 90;

/* ── Constantes ─────────────────────────────────────────── */
const STATUS_LABEL: Record<string, string> = {
  scheduled:  'Agendada',
  work_order: 'En proceso',
  completed:  'Completada',
  cancelled:  'Cancelada',
  no_show:    'No se presentó',
};
const STATUS_BG: Record<string, string> = {
  scheduled:  'text-primary bg-primary/10',
  work_order: 'text-secondary bg-secondary/10',
  completed:  'text-tertiary bg-tertiary/10',
  cancelled:  'text-error bg-error/10',
  no_show:    'text-error bg-error/10',
};

function shortDate(ymd: string): string {
  const [y, mo, d] = ymd.split('-');
  return `${d}/${mo}/${y.slice(2)}`;
}

/* ══════════════════════════════════════════════════════════ */
export function MobPetJobs() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const [petName,      setPetName]      = useState('');
  const [bookings,     setBookings]     = useState<JobBooking[]>([]);
  const [loading,      setLoading]      = useState(true);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('pendientes');
  const [dateRange,    setDateRange]    = useState<DateRange>(0);

  /* Carga inicial: nombre de mascota + historial */
  useEffect(() => {
    if (!id) return;
    Promise.all([
      fetch(`/api/pets/${id}`).then(r => r.ok ? r.json() : { name: '' }),
      fetch(`/api/pets/${id}/bookings`).then(r => r.ok ? r.json() : []),
    ])
      .then(([pet, bkgs]) => {
        setPetName(pet.name ?? '');
        setBookings(bkgs);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [id]);

  /* Filas de la tabla */
  const jobRows = useMemo<JobRow[]>(() =>
    bookings.map(b => ({
      key:        String(b.id),
      id:         b.id,
      fecha_iso:  b.fecha_iso,
      fecha_short: shortDate(b.fecha),
      status:     b.status,
      has_folio:  !!b.order_folio,
      folio_label: b.order_folio ?? (STATUS_LABEL[b.status] ?? b.status),
      servicios:  b.services.map(s => s.name).join(' · ') || '—',
      total:      b.services.reduce((s, x) => s + x.price, 0) ||
                  b.payments.reduce((s, p) => s + p.amount, 0),
    })),
  [bookings]);

  /* Aplicar filtros */
  const filteredRows = useMemo<JobRow[]>(() => {
    let rows = jobRows;
    if (dateRange > 0) {
      const cutoff = new Date();
      cutoff.setDate(cutoff.getDate() - dateRange);
      rows = rows.filter(r => new Date(r.fecha_iso) >= cutoff);
    }
    switch (statusFilter) {
      case 'pendientes':  return rows.filter(r => ['scheduled', 'work_order'].includes(r.status));
      case 'completadas': return rows.filter(r => r.status === 'completed');
      case 'canceladas':  return rows.filter(r => ['cancelled', 'no_show'].includes(r.status));
      default:            return rows;
    }
  }, [jobRows, statusFilter, dateRange]);

  const { sortKey, direction, toggle, sorted } =
    useSortable<JobRow>(filteredRows, 'fecha_iso', 'desc');

  /* ── Render ─────────────────────────────────────────────── */
  if (loading) return (
    <div className="min-h-screen bg-background flex items-center justify-center">
      <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
    </div>
  );

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">

      {/* ── Header ──────────────────────────────────────────── */}
      <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
        <button onClick={() => navigate(`/mascotas/${id}`)}
          className="p-2 rounded-full hover:bg-surface-container-high transition-colors">
          <span className="material-symbols-outlined text-on-surface">arrow_back</span>
        </button>
        <div className="flex-1 min-w-0">
          <p className="font-bold text-on-surface text-base leading-tight truncate">
            Trabajos
            <span className="text-[9px] font-mono text-on-surface-variant/30 font-normal ml-1">MobPetJobs</span>
          </p>
          {petName && <p className="text-xs text-on-surface-variant truncate">{petName}</p>}
        </div>
        <span className="text-xs text-on-surface-variant/50 shrink-0">
          {sorted.length} {sorted.length === 1 ? 'reg.' : 'regs.'}
        </span>
      </header>

      {/* ── Filtros de estado ────────────────────────────────── */}
      <div className="bg-surface border-b border-outline-variant px-3 py-2 flex items-center gap-2 overflow-x-auto hide-scrollbar">
        {(['pendientes', 'completadas', 'canceladas', 'todas'] as StatusFilter[]).map(f => (
          <button key={f} onClick={() => setStatusFilter(f)}
            className={`px-3 py-1.5 rounded-full text-xs font-semibold shrink-0 border transition-colors ${
              statusFilter === f
                ? 'bg-primary text-on-primary border-primary'
                : 'bg-surface-container text-on-surface-variant border-outline-variant'
            }`}>
            {{ pendientes: 'Pendientes', completadas: 'Completadas', canceladas: 'Canceladas', todas: 'Todas' }[f]}
          </button>
        ))}
      </div>

      {/* ── Filtro de período (solo si no es pendientes) ─────── */}
      {statusFilter !== 'pendientes' && (
        <div className="bg-surface border-b border-outline-variant px-3 py-1.5 flex items-center gap-2 overflow-x-auto hide-scrollbar">
          <span className="text-[10px] text-on-surface-variant/60 shrink-0 uppercase tracking-wide">Período:</span>
          {([30, 90, 0] as DateRange[]).map(d => (
            <button key={d} onClick={() => setDateRange(d)}
              className={`px-3 py-1 rounded-full text-xs font-medium shrink-0 border transition-colors ${
                dateRange === d
                  ? 'bg-secondary text-on-secondary border-secondary'
                  : 'bg-surface-container text-on-surface-variant border-outline-variant'
              }`}>
              {d === 0 ? 'Todo' : `${d} días`}
            </button>
          ))}
        </div>
      )}

      {/* ── Tabla ────────────────────────────────────────────── */}
      {sorted.length === 0 ? (
        <div className="flex-1 flex flex-col items-center justify-center gap-3 py-16 text-center px-8">
          <span className="material-symbols-outlined text-5xl text-on-surface-variant/30"
            style={{ fontVariationSettings: "'FILL' 1" }}>work_history</span>
          <p className="text-sm text-on-surface-variant">Sin trabajos para este filtro</p>
          <button onClick={() => { setStatusFilter('todas'); setDateRange(0); }}
            className="text-sm text-primary font-semibold">Ver todos los trabajos</button>
        </div>
      ) : (
        <div className="flex-1 overflow-auto">
          <table className="w-full text-sm border-collapse">
            <thead className="sticky top-0 z-10">
              <tr className="bg-surface-container border-b-2 border-outline-variant">
                <th className="px-3 py-2.5 text-left w-[76px]">
                  <SortBtn label="Fecha" col="fecha_iso" sortKey={sortKey} direction={direction} onToggle={toggle} />
                </th>
                <th className="px-2 py-2.5 text-left w-[108px]">
                  <SortBtn label="Ref" col="folio_label" sortKey={sortKey} direction={direction} onToggle={toggle} />
                </th>
                <th className="px-2 py-2.5 text-left">
                  <SortBtn label="Servicios" col="servicios" sortKey={sortKey} direction={direction} onToggle={toggle} />
                </th>
                <th className="px-3 py-2.5 w-16">
                  <SortBtn label="Total" col="total" sortKey={sortKey} direction={direction} onToggle={toggle} className="justify-end" />
                </th>
              </tr>
            </thead>
            <tbody>
              {sorted.map((row, i) => (
                <tr key={row.key} onClick={() => navigate(`/citas/${row.id}`)}
                  className={`cursor-pointer active:bg-surface-container transition-colors ${
                    i > 0 ? 'border-t border-outline-variant' : ''
                  }`}>
                  {/* Fecha */}
                  <td className="px-3 py-2.5 w-[76px]">
                    <p className="text-xs font-mono text-on-surface leading-tight">{row.fecha_short}</p>
                  </td>
                  {/* Referencia / Folio */}
                  <td className="px-2 py-2.5 w-[108px]">
                    <span className={`text-[11px] font-bold px-1.5 py-0.5 rounded-md inline-block max-w-full truncate ${
                      row.has_folio
                        ? 'font-mono text-primary bg-primary/10'
                        : (STATUS_BG[row.status] ?? 'text-on-surface-variant bg-surface-container')
                    }`}>
                      {row.folio_label}
                    </span>
                  </td>
                  {/* Servicios */}
                  <td className="px-2 py-2.5">
                    <p className="text-sm text-on-surface truncate">{row.servicios}</p>
                  </td>
                  {/* Total */}
                  <td className="px-3 py-2.5 text-right w-16">
                    {row.total > 0 && (
                      <span className="text-sm font-bold text-on-surface">${row.total.toFixed(0)}</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

    </div>
  );
}
