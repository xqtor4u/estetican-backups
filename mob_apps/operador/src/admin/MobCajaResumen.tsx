import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';
import { useAuth } from '../AuthContext';
import { ReportActionsBar } from './cajaReportShared';

/**
 * Primer reporte real de Caja — consume `/api/cash/reports/resumen`, que ya trae los datos
 * agregados del lado del servidor (mismo cálculo que usan `resumenPdf()`/`resumenEmail()`) en
 * vez de volver a agregar `movements()` crudo acá: la primera versión de esta pantalla SÍ
 * agregaba en el cliente, y terminó con un bug real (agrupar por `type` sin `direction`
 * mezclaba un movimiento con su reversión) que también apareció, por separado, del lado del
 * PDF — dos implementaciones de la misma cuenta divergieron. Una sola fuente de verdad lo evita.
 *
 * Deliberadamente sin preset "Turno actual": `/api/cash/session` (la fuente de "saldo
 * esperado") solo trae movimientos manuales de `CashMovement`, nunca cobros — mezclar "Total
 * cobrado" con un turno abierto daría un número que no reconcilia con nada real. Se queda en
 * Hoy/Semana/Mes/Rango, igual que el historial.
 */
interface TypeGroup { type: string; label: string; count: number; amount: number }
interface ResumenData {
  totalCobrado: number;
  totalEntradas: number;
  totalSalidas: number;
  neto: number;
  byTypeEntradas: TypeGroup[];
  byTypeSalidas: TypeGroup[];
  canSelectBranch: boolean;
}
interface Branch { id: number; name: string }

type Preset = 'hoy' | 'semana' | 'mes' | 'custom';

const PRESETS: { key: Preset; label: string }[] = [
  { key: 'hoy',    label: 'Hoy' },
  { key: 'semana', label: 'Semana' },
  { key: 'mes',    label: 'Mes' },
  { key: 'custom', label: 'Personalizado' },
];

function fmtMoney(n: number) {
  return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function toDateStr(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function today()        { return toDateStr(new Date()); }
function startOfWeek()  {
  const d = new Date(), day = d.getDay();
  d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
  return toDateStr(d);
}
function startOfMonth() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
}

function StatTile({ label, value, tone }: { label: string; value: number; tone: 'primary' | 'error' | 'neutral' }) {
  const color = tone === 'primary' ? 'text-primary' : tone === 'error' ? 'text-error' : 'text-on-surface';
  const bg = tone === 'primary' ? 'bg-primary/10' : tone === 'error' ? 'bg-error/10' : 'bg-surface-container';
  return (
    <div className={`${bg} rounded-2xl px-4 py-3`}>
      <p className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wide mb-0.5">{label}</p>
      <p className={`text-lg font-bold ${color}`}>{fmtMoney(value)}</p>
    </div>
  );
}

function TypeBreakdown({ title, groups, emptyLabel }: { title: string; groups: TypeGroup[]; emptyLabel: string }) {
  return (
    <div className="bg-surface-container rounded-2xl overflow-hidden">
      <div className="px-4 py-2.5 border-b border-outline-variant">
        <p className="text-xs font-bold text-on-surface-variant uppercase tracking-widest">{title}</p>
      </div>
      {groups.length === 0 ? (
        <div className="px-4 py-6 text-center">
          <p className="text-sm text-on-surface-variant">{emptyLabel}</p>
        </div>
      ) : (
        groups.map((g, i) => (
          <div key={g.type}
            className={`flex items-center justify-between px-4 py-3 ${i < groups.length - 1 ? 'border-b border-outline-variant' : ''}`}>
            <div className="flex-1 min-w-0">
              <p className="text-sm text-on-surface">{g.label}</p>
              <p className="text-[11px] text-on-surface-variant">{g.count} mov.</p>
            </div>
            <p className="text-sm font-semibold text-on-surface shrink-0">{fmtMoney(g.amount)}</p>
          </div>
        ))
      )}
    </div>
  );
}

export function MobCajaResumen() {
  const navigate = useNavigate();
  const crumbs = getNavCrumbs();
  const { showBreadcrumbs } = getUserPrefs();
  const { user } = useAuth();

  const [preset,   setPreset]   = useState<Preset>('hoy');
  const [dateFrom, setDateFrom] = useState(today());
  const [dateTo,   setDateTo]   = useState(today());
  const [branchId, setBranchId] = useState<number | ''>('');
  const [branches, setBranches] = useState<Branch[]>([]);
  const [result,   setResult]   = useState<ResumenData | null>(null);
  const [loading,  setLoading]  = useState(false);
  const [error,    setError]    = useState<string | null>(null);

  const applyPreset = (p: Preset) => {
    setPreset(p);
    if (p === 'hoy')    { setDateFrom(today());        setDateTo(today()); }
    if (p === 'semana') { setDateFrom(startOfWeek());  setDateTo(today()); }
    if (p === 'mes')    { setDateFrom(startOfMonth()); setDateTo(today()); }
  };

  // Mismo criterio que MobCajaMovimientos: el <select> de sucursal solo tiene sentido para
  // super-admin, y solo se puebla si de verdad tiene permission:ver sucursales.
  useEffect(() => {
    if (!user?.is_admin) return;
    fetch('/api/branches')
      .then(res => res.ok ? res.json() : [])
      .then(setBranches)
      .catch(() => {});
  }, [user?.is_admin]);

  const queryParams = () => {
    const params = new URLSearchParams({ date_from: dateFrom, date_to: dateTo });
    if (branchId !== '') params.set('branch_id', String(branchId));
    return params;
  };

  const fetchResumen = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`/api/cash/reports/resumen?${queryParams()}`);
      if (res.status === 422) { setError('No tienes una sucursal asignada — pídele a un administrador que te la asigne.'); return; }
      if (!res.ok)            { setError('Error al cargar el resumen.'); return; }
      setResult(await res.json());
    } catch {
      setError('No se pudo conectar con el servidor.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchResumen(); }, [dateFrom, dateTo, branchId]);

  return (
    <div className="min-h-screen bg-background pb-16">
      <ScreenHeader
        title="Resumen de caja"
        screenTag="MobCajaResumen"
        crumbs={crumbs}
        showBreadcrumbs={showBreadcrumbs}
        onBack={() => navigate(-1)}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); }}
      />

      {/* Presets de fecha */}
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

      {/* Rango de fechas */}
      <div className="flex gap-2 px-4 pb-3">
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

      {/* Filtro por sucursal (solo super-admin) */}
      {result?.canSelectBranch && (
        <div className="px-4 pb-3">
          <label className="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider block mb-1 pl-1">Sucursal</label>
          <select value={branchId} onChange={e => setBranchId(e.target.value === '' ? '' : Number(e.target.value))}
            className="w-full bg-surface-container rounded-xl px-3 py-2 text-sm text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
            <option value="">Todas las sucursales</option>
            {branches.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
          </select>
        </div>
      )}

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
            <button onClick={fetchResumen}
              className="mt-3 px-5 py-2 rounded-xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform">
              Reintentar
            </button>
          </div>
        )}

        {!loading && !error && result && (
          <>
            {/* Tarjetas principales */}
            <div className="grid grid-cols-2 gap-3">
              <StatTile label="Total cobrado" value={result.totalCobrado} tone="primary" />
              <StatTile label="Neto del período" value={result.neto} tone={result.neto >= 0 ? 'primary' : 'error'} />
              <StatTile label="Entradas" value={result.totalEntradas} tone="neutral" />
              <StatTile label="Salidas" value={result.totalSalidas} tone="neutral" />
            </div>

            {/* Desglose por tipo — Entradas / Salidas por separado, nunca mezclados */}
            <TypeBreakdown title="Desglose — Entradas" groups={result.byTypeEntradas} emptyLabel="Sin entradas para este período" />
            <TypeBreakdown title="Desglose — Salidas" groups={result.byTypeSalidas} emptyLabel="Sin salidas para este período" />

            <ReportActionsBar
              pdfUrl={`/api/cash/reports/resumen/pdf?${queryParams()}`}
              emailUrl={`/api/cash/reports/resumen/email?${queryParams()}`}
              filename={`resumen-caja-${dateFrom}-a-${dateTo}.pdf`}
              defaultEmail={user?.email ?? ''}
            />
          </>
        )}
      </div>
    </div>
  );
}
