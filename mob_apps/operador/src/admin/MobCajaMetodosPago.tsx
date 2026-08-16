import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';
import { useAuth } from '../AuthContext';
import { ReportActionsBar } from './cajaReportShared';

/**
 * Segundo reporte real de Caja — desglosa solo los cobros (cobro_efectivo/cobro_banco) por el
 * método de pago real (Efectivo, Tarjeta, Transferencia, etc.), nunca los movimientos manuales
 * de caja: esos no tienen "método de pago" en este sentido, tienen cuenta contable contraparte
 * (ver "Resumen de caja" para ese desglose). Consume `/api/cash/reports/metodos-pago`, ya
 * agregado del lado del servidor — mismo criterio que `MobCajaResumen.tsx`.
 */
interface MethodGroup { method: string; count: number; amount: number }
interface MetodosPagoData {
  totalCobrado: number;
  byMethod: MethodGroup[];
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

export function MobCajaMetodosPago() {
  const navigate = useNavigate();
  const crumbs = getNavCrumbs();
  const { showBreadcrumbs } = getUserPrefs();
  const { user } = useAuth();

  const [preset,   setPreset]   = useState<Preset>('hoy');
  const [dateFrom, setDateFrom] = useState(today());
  const [dateTo,   setDateTo]   = useState(today());
  const [branchId, setBranchId] = useState<number | ''>('');
  const [branches, setBranches] = useState<Branch[]>([]);
  const [result,   setResult]   = useState<MetodosPagoData | null>(null);
  const [loading,  setLoading]  = useState(false);
  const [error,    setError]    = useState<string | null>(null);

  const applyPreset = (p: Preset) => {
    setPreset(p);
    if (p === 'hoy')    { setDateFrom(today());        setDateTo(today()); }
    if (p === 'semana') { setDateFrom(startOfWeek());  setDateTo(today()); }
    if (p === 'mes')    { setDateFrom(startOfMonth()); setDateTo(today()); }
  };

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

  const fetchMetodos = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`/api/cash/reports/metodos-pago?${queryParams()}`);
      if (res.status === 422) { setError('No tienes una sucursal asignada — pídele a un administrador que te la asigne.'); return; }
      if (!res.ok)            { setError('Error al cargar el reporte.'); return; }
      setResult(await res.json());
    } catch {
      setError('No se pudo conectar con el servidor.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchMetodos(); }, [dateFrom, dateTo, branchId]);

  const maxAmount = Math.max(1, ...(result?.byMethod.map(g => g.amount) ?? [1]));

  return (
    <div className="min-h-screen bg-background pb-16">
      <ScreenHeader
        title="Métodos de pago"
        screenTag="MobCajaMetodosPago"
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
          <p className="text-[10px] text-on-surface-variant/70 mt-1 pl-1">
            Solo incluye cobros a clientes — los movimientos manuales de caja no tienen un método
            de pago en este sentido.
          </p>
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
            <button onClick={fetchMetodos}
              className="mt-3 px-5 py-2 rounded-xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform">
              Reintentar
            </button>
          </div>
        )}

        {!loading && !error && result && (
          <>
            <div className="bg-primary/10 rounded-2xl px-4 py-3">
              <p className="text-[10px] font-semibold text-primary uppercase tracking-wide mb-0.5">Total cobrado</p>
              <p className="text-xl font-bold text-primary">{fmtMoney(result.totalCobrado)}</p>
            </div>

            <div className="bg-surface-container rounded-2xl overflow-hidden">
              <div className="px-4 py-2.5 border-b border-outline-variant">
                <p className="text-xs font-bold text-on-surface-variant uppercase tracking-widest">Desglose por método</p>
              </div>
              {result.byMethod.length === 0 ? (
                <div className="px-4 py-6 text-center">
                  <p className="text-sm text-on-surface-variant">Sin cobros para este período</p>
                </div>
              ) : (
                result.byMethod.map((g, i) => (
                  <div key={g.method}
                    className={`px-4 py-3 ${i < result.byMethod.length - 1 ? 'border-b border-outline-variant' : ''}`}>
                    <div className="flex items-center justify-between mb-1.5">
                      <div className="min-w-0">
                        <p className="text-sm text-on-surface">{g.method}</p>
                        <p className="text-[11px] text-on-surface-variant">{g.count} cobro{g.count === 1 ? '' : 's'}</p>
                      </div>
                      <p className="text-sm font-semibold text-on-surface shrink-0">{fmtMoney(g.amount)}</p>
                    </div>
                    <div className="h-1.5 bg-outline-variant/30 rounded-full overflow-hidden">
                      <div className="h-full bg-primary rounded-full" style={{ width: `${(g.amount / maxAmount) * 100}%` }} />
                    </div>
                  </div>
                ))
              )}
            </div>

            <ReportActionsBar
              pdfUrl={`/api/cash/reports/metodos-pago/pdf?${queryParams()}`}
              emailUrl={`/api/cash/reports/metodos-pago/email?${queryParams()}`}
              filename={`metodos-pago-${dateFrom}-a-${dateTo}.pdf`}
              defaultEmail={user?.email ?? ''}
            />
          </>
        )}
      </div>
    </div>
  );
}
