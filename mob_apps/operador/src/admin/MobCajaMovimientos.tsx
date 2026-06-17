import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';

/* ── Tipos ────────────────────────────────────────────────── */
interface Movement {
  id: string;
  type: string;
  direction: 'entrada' | 'salida';
  amount: number;
  concept: string;
  notes: string | null;
  account: string | null;
  client_name: string | null;
  created_at: string;
}
interface Totals {
  total_entradas: number;
  total_salidas:  number;
  count:          number;
}
interface ApiResult {
  movements: Movement[];
  totals:    Totals;
}

type Preset = 'hoy' | 'semana' | 'mes' | 'custom';

/* ── Constantes ──────────────────────────────────────────── */
const TYPE_LABELS: Record<string, string> = {
  cobro_efectivo: 'Cobros efectivo',
  cobro_banco:    'Cobros banco/tarjeta',
  entrada:        'Entrada efectivo',
  retiro:         'Retiro',
  deposito_banco: 'Depósito banco',
  gasto:          'Gasto',
  perdida:        'Pérdida',
};
const TYPE_ICONS: Record<string, string> = {
  cobro_efectivo: 'payments',
  cobro_banco:    'credit_card',
  entrada:        'add_circle',
  retiro:         'account_balance_wallet',
  deposito_banco: 'account_balance',
  gasto:          'receipt_long',
  perdida:        'money_off',
};
const TYPE_ORDER = ['cobro_efectivo', 'cobro_banco', 'entrada', 'retiro', 'deposito_banco', 'gasto', 'perdida'];

const PRESETS: { key: Preset; label: string }[] = [
  { key: 'hoy',    label: 'Hoy' },
  { key: 'semana', label: 'Semana' },
  { key: 'mes',    label: 'Mes' },
  { key: 'custom', label: 'Personalizado' },
];
const TYPE_FILTERS = [
  { value: '',               label: 'Todos' },
  { value: 'cobro_efectivo', label: 'Cobros efectivo' },
  { value: 'cobro_banco',    label: 'Cobros banco' },
  { value: 'entrada',        label: 'Entrada' },
  { value: 'retiro',         label: 'Retiro' },
  { value: 'deposito_banco', label: 'Dep. banco' },
  { value: 'gasto',          label: 'Gasto' },
  { value: 'perdida',        label: 'Pérdida' },
];

/* ── Helpers ─────────────────────────────────────────────── */
function fmtMoney(n: number) {
  return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function fmtDateTime(iso: string) {
  return new Date(iso).toLocaleString('es-MX', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  });
}
function today()        { return new Date().toISOString().slice(0, 10); }
function startOfWeek()  {
  const d = new Date(), day = d.getDay();
  d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
  return d.toISOString().slice(0, 10);
}
function startOfMonth() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
}

/* ── Fila de movimiento individual ───────────────────────── */
function MovementRow({ m, last }: { m: Movement; last: boolean }) {
  return (
    <div className={`flex items-start gap-3 px-4 py-3 ${last ? '' : 'border-b border-outline-variant'}`}>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-on-surface truncate">
          {m.client_name ?? m.concept}
        </p>
        <p className="text-xs text-on-surface-variant">
          {m.account ?? '—'}{' · '}{fmtDateTime(m.created_at)}
        </p>
        {m.notes && <p className="text-xs text-on-surface-variant/70 truncate">{m.notes}</p>}
      </div>
      <p className={`text-sm font-semibold shrink-0 ${m.direction === 'entrada' ? 'text-primary' : 'text-error'}`}>
        {m.direction === 'entrada' ? '+' : '-'}{fmtMoney(m.amount)}
      </p>
    </div>
  );
}

/* ── Cabecera de grupo dentro de una sección ─────────────── */
function GroupHeader({ type, items, color }: {
  type: string;
  items: Movement[];
  color: 'primary' | 'error';
}) {
  const sub = items.reduce((s, m) => s + m.amount, 0);
  return (
    <div className={`flex items-center gap-2 px-4 py-2 border-b border-outline-variant/60 ${
      color === 'primary' ? 'bg-primary/[0.04]' : 'bg-error/[0.04]'
    }`}>
      <span
        className="material-symbols-outlined text-sm shrink-0"
        style={{ color: `var(--color-${color})`, fontVariationSettings: "'FILL' 1" }}
      >
        {TYPE_ICONS[type] ?? 'swap_horiz'}
      </span>
      <p className="flex-1 text-xs font-semibold text-on-surface-variant">{TYPE_LABELS[type] ?? type}</p>
      <p className="text-[11px] text-on-surface-variant/70 mr-2">{items.length} mov.</p>
      <p className={`text-xs font-bold text-${color}`}>{fmtMoney(sub)}</p>
    </div>
  );
}

/* ── Vista balance + detalle ─────────────────────────────── */
function BalanceDetailView({ movements, totals }: { movements: Movement[]; totals: Totals }) {
  const neto = totals.total_entradas - totals.total_salidas;

  const entradaGroups = TYPE_ORDER
    .map(t => ({ type: t, items: movements.filter(m => m.type === t && m.direction === 'entrada') }))
    .filter(g => g.items.length > 0);

  const salidaGroups = TYPE_ORDER
    .map(t => ({ type: t, items: movements.filter(m => m.type === t && m.direction === 'salida') }))
    .filter(g => g.items.length > 0);

  return (
    <>
      {/* Resumen de balance */}
      <div className="grid grid-cols-3 gap-2">
        <div className="bg-primary/10 rounded-2xl px-3 py-3">
          <p className="text-[10px] font-semibold text-primary uppercase tracking-wide mb-0.5">Entradas</p>
          <p className="text-sm font-bold text-primary">+{fmtMoney(totals.total_entradas)}</p>
        </div>
        <div className="bg-error/10 rounded-2xl px-3 py-3">
          <p className="text-[10px] font-semibold text-error uppercase tracking-wide mb-0.5">Salidas</p>
          <p className="text-sm font-bold text-error">-{fmtMoney(totals.total_salidas)}</p>
        </div>
        <div className={`${neto >= 0 ? 'bg-primary/5' : 'bg-error/5'} rounded-2xl px-3 py-3`}>
          <p className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wide mb-0.5">Neto</p>
          <p className={`text-sm font-bold ${neto >= 0 ? 'text-primary' : 'text-error'}`}>
            {neto >= 0 ? '+' : ''}{fmtMoney(neto)}
          </p>
        </div>
      </div>

      {/* Sección ENTRADAS */}
      {entradaGroups.length > 0 && (
        <div className="bg-surface-container rounded-2xl overflow-hidden">
          <div className="flex items-center px-4 py-2.5 bg-primary/5 border-b border-outline-variant">
            <p className="flex-1 text-xs font-bold text-primary uppercase tracking-widest">Entradas</p>
            <p className="text-sm font-bold text-primary">+{fmtMoney(totals.total_entradas)}</p>
          </div>
          {entradaGroups.map(({ type, items }) => (
            <React.Fragment key={type}>
              <GroupHeader type={type} items={items} color="primary" />
              {items.map((m, i) => (
                <MovementRow key={m.id} m={m} last={i === items.length - 1} />
              ))}
            </React.Fragment>
          ))}
        </div>
      )}

      {/* Sección SALIDAS */}
      {salidaGroups.length > 0 && (
        <div className="bg-surface-container rounded-2xl overflow-hidden">
          <div className="flex items-center px-4 py-2.5 bg-error/5 border-b border-outline-variant">
            <p className="flex-1 text-xs font-bold text-error uppercase tracking-widest">Salidas</p>
            <p className="text-sm font-bold text-error">-{fmtMoney(totals.total_salidas)}</p>
          </div>
          {salidaGroups.map(({ type, items }) => (
            <React.Fragment key={type}>
              <GroupHeader type={type} items={items} color="error" />
              {items.map((m, i) => (
                <MovementRow key={m.id} m={m} last={i === items.length - 1} />
              ))}
            </React.Fragment>
          ))}
        </div>
      )}

      {/* Neto final */}
      <div className={`rounded-2xl px-4 py-4 flex items-center ${neto >= 0 ? 'bg-primary/5' : 'bg-error/5'}`}>
        <p className="flex-1 text-base font-bold text-on-surface">Neto del período</p>
        <p className={`text-base font-bold ${neto >= 0 ? 'text-primary' : 'text-error'}`}>
          {neto >= 0 ? '+' : ''}{fmtMoney(neto)}
        </p>
      </div>
    </>
  );
}

/* ══════════════════════════════════════════════════════════ */
export function MobCajaMovimientos() {
  const navigate = useNavigate();
  const crumbs = getNavCrumbs();
  const { showBreadcrumbs } = getUserPrefs();

  const [preset,     setPreset]     = useState<Preset>('mes');
  const [dateFrom,   setDateFrom]   = useState(startOfMonth());
  const [dateTo,     setDateTo]     = useState(today());
  const [typeFilter, setTypeFilter] = useState('');
  const [result,     setResult]     = useState<ApiResult | null>(null);
  const [loading,    setLoading]    = useState(false);
  const [error,      setError]      = useState<string | null>(null);

  const applyPreset = (p: Preset) => {
    setPreset(p);
    if (p === 'hoy')    { setDateFrom(today());        setDateTo(today()); }
    if (p === 'semana') { setDateFrom(startOfWeek());  setDateTo(today()); }
    if (p === 'mes')    { setDateFrom(startOfMonth()); setDateTo(today()); }
  };

  const fetchMovements = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({ date_from: dateFrom, date_to: dateTo });
      if (typeFilter) params.set('type', typeFilter);
      const res = await fetch(`/api/cash/movements?${params}`);
      if (res.status === 403) { setError('Sin check-in activo en ninguna sucursal.'); return; }
      if (!res.ok)            { setError('Error al cargar movimientos.'); return; }
      setResult(await res.json());
    } catch {
      setError('No se pudo conectar con el servidor.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchMovements(); }, [dateFrom, dateTo, typeFilter]);

  return (
    <div className="min-h-screen bg-background pb-24">
      <ScreenHeader
        title="Balance de movimientos"
        screenTag="MobCajaMovimientos"
        crumbs={crumbs}
        showBreadcrumbs={showBreadcrumbs}
        onBack={() => navigate(-1)}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); }}
      />

      {/* ── Presets de fecha ──────────────────────────────── */}
      <div className="flex gap-2 px-4 pt-3 pb-2 overflow-x-auto scrollbar-none">
        {PRESETS.map(p => (
          <button key={p.key} onClick={() => applyPreset(p.key)}
            className={`shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors ${
              preset === p.key ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant'
            }`}>
            {p.label}
          </button>
        ))}
      </div>

      {/* ── Rango de fechas ───────────────────────────────── */}
      <div className="flex gap-2 px-4 pb-2">
        <div className="flex-1">
          <label className="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider block mb-1 pl-1">Desde</label>
          <input type="date" value={dateFrom} max={dateTo}
            onChange={e => { setDateFrom(e.target.value); setPreset('custom'); }}
            className="w-full bg-surface-container rounded-xl px-3 py-2 text-sm text-on-surface border border-outline-variant focus:outline-none focus:border-primary" />
        </div>
        <div className="flex-1">
          <label className="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider block mb-1 pl-1">Hasta</label>
          <input type="date" value={dateTo} min={dateFrom} max={today()}
            onChange={e => { setDateTo(e.target.value); setPreset('custom'); }}
            className="w-full bg-surface-container rounded-xl px-3 py-2 text-sm text-on-surface border border-outline-variant focus:outline-none focus:border-primary" />
        </div>
      </div>

      {/* ── Filtro por tipo ───────────────────────────────── */}
      <div className="flex gap-2 px-4 pb-3 overflow-x-auto scrollbar-none">
        {TYPE_FILTERS.map(f => (
          <button key={f.value} onClick={() => setTypeFilter(f.value)}
            className={`shrink-0 px-3 py-1 rounded-full text-xs font-medium transition-colors ${
              typeFilter === f.value ? 'bg-secondary text-on-secondary' : 'bg-surface-container text-on-surface-variant'
            }`}>
            {f.label}
          </button>
        ))}
      </div>

      {/* ── Contenido ─────────────────────────────────────── */}
      <div className="mx-4 flex flex-col gap-4">
        {loading && (
          <div className="flex justify-center py-10">
            <span className="material-symbols-outlined text-3xl text-on-surface-variant animate-spin">progress_activity</span>
          </div>
        )}

        {!loading && error && (
          <div className="bg-error-container rounded-2xl px-4 py-5 text-center">
            <span className="material-symbols-outlined text-3xl text-error mb-2 block">error</span>
            <p className="text-sm text-on-error-container">{error}</p>
            <button onClick={fetchMovements}
              className="mt-3 px-5 py-2 rounded-xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform">
              Reintentar
            </button>
          </div>
        )}

        {!loading && result && result.movements.length === 0 && (
          <div className="bg-surface-container rounded-2xl px-4 py-8 text-center">
            <span className="material-symbols-outlined text-4xl text-on-surface-variant">receipt_long</span>
            <p className="text-sm text-on-surface-variant mt-2">Sin movimientos para este período</p>
          </div>
        )}

        {!loading && result && result.movements.length > 0 && (
          <BalanceDetailView movements={result.movements} totals={result.totals} />
        )}
      </div>
    </div>
  );
}
