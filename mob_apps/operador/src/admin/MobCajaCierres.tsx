import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';
import { useAuth } from '../AuthContext';
import { ReportActionsBar } from './cajaReportShared';

/**
 * Quinto y último reporte del primer corte móvil — lista de cierres reales de turno. Lee
 * directo `cash_sessions.{opening_amount,expected_amount,closing_amount,difference}`, los
 * mismos valores que ya calculó y guardó `Finances\CashSessionController::doClose()` al cerrar
 * de verdad — nunca se recalculan acá (ver la nota completa en
 * `CashController::buildCierresData()` sobre la discrepancia conocida y no resuelta entre esa
 * fórmula y `saldo_esperado` del turno todavía abierto).
 */
interface CierreItem {
  branchName: string;
  closedBy: string;
  closedAt: string;
  openingAmount: number;
  expectedAmount: number;
  closingAmount: number;
  difference: number;
}
interface CierresData {
  items: CierreItem[];
  totalDifference: number;
  count: number;
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
/** El signo va antes del "$", nunca "$-5.00" — mismo criterio que el lado PHP (cierres-pdf.blade.php). */
function fmtSigned(n: number) {
  return (n >= 0 ? '+' : '-') + '$' + Math.abs(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
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

export function MobCajaCierres() {
  const navigate = useNavigate();
  const crumbs = getNavCrumbs();
  const { showBreadcrumbs } = getUserPrefs();
  const { user } = useAuth();

  const [preset,   setPreset]   = useState<Preset>('mes');
  const [dateFrom, setDateFrom] = useState(startOfMonth());
  const [dateTo,   setDateTo]   = useState(today());
  const [branchId, setBranchId] = useState<number | ''>('');
  const [branches, setBranches] = useState<Branch[]>([]);
  const [result,   setResult]   = useState<CierresData | null>(null);
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

  const fetchReport = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`/api/cash/reports/cierres?${queryParams()}`);
      if (!res.ok) { setError('Error al cargar el reporte.'); return; }
      setResult(await res.json());
    } catch {
      setError('No se pudo conectar con el servidor.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchReport(); }, [dateFrom, dateTo, branchId]);

  return (
    <div className="min-h-screen bg-background pb-16">
      <ScreenHeader
        title="Cierre de turno"
        screenTag="MobCajaCierres"
        crumbs={crumbs}
        showBreadcrumbs={showBreadcrumbs}
        onBack={() => navigate(-1)}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); }}
      />

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
            <button onClick={fetchReport}
              className="mt-3 px-5 py-2 rounded-xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform">
              Reintentar
            </button>
          </div>
        )}

        {!loading && !error && result && (
          <>
            <div className={`${result.totalDifference >= 0 ? 'bg-primary/10' : 'bg-error/10'} rounded-2xl px-4 py-3`}>
              <p className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wide mb-0.5">Diferencia acumulada</p>
              <p className={`text-xl font-bold ${result.totalDifference >= 0 ? 'text-primary' : 'text-error'}`}>{fmtSigned(result.totalDifference)}</p>
              <p className="text-xs text-on-surface-variant mt-0.5">{result.count} cierre{result.count === 1 ? '' : 's'}</p>
            </div>

            <div className="bg-surface-container rounded-2xl overflow-hidden">
              {result.items.length === 0 ? (
                <div className="px-4 py-6 text-center">
                  <p className="text-sm text-on-surface-variant">Sin cierres de turno para este período</p>
                </div>
              ) : (
                result.items.map((item, i) => (
                  <div key={i}
                    className={`px-4 py-3 ${i < result.items.length - 1 ? 'border-b border-outline-variant' : ''}`}>
                    <div className="flex items-center justify-between mb-1">
                      {/* Sucursal repetida en cada renglón solo tiene sentido viendo "todas las
                          sucursales" a la vez — con una sola seleccionada (o un operador normal,
                          que nunca ve más de la suya) es ruido puro, ya está en el filtro de
                          arriba. Hallazgo real (19/08/2026), reportado por Tomas armando demo. */}
                      {!branchId && result.canSelectBranch ? (
                        <p className="text-sm font-medium text-on-surface">{item.branchName}</p>
                      ) : (
                        <p className="text-sm font-medium text-on-surface">{item.closedBy}</p>
                      )}
                      <p className={`text-sm font-bold ${item.difference >= 0 ? 'text-primary' : 'text-error'}`}>{fmtSigned(item.difference)}</p>
                    </div>
                    <p className="text-xs text-on-surface-variant">
                      {item.closedAt}{(!branchId && result.canSelectBranch) ? ` · ${item.closedBy}` : ''}
                    </p>
                    <p className="text-[11px] text-on-surface-variant mt-0.5">
                      Inicial {fmtMoney(item.openingAmount)} · Esperado {fmtMoney(item.expectedAmount)} · Contado {fmtMoney(item.closingAmount)}
                    </p>
                  </div>
                ))
              )}
            </div>

            <ReportActionsBar
              pdfUrl={`/api/cash/reports/cierres/pdf?${queryParams()}`}
              emailUrl={`/api/cash/reports/cierres/email?${queryParams()}`}
              filename={`cierres-de-turno-${dateFrom}-a-${dateTo}.pdf`}
              defaultEmail={user?.email ?? ''}
            />
          </>
        )}
      </div>
    </div>
  );
}
