import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';
import { useAuth } from '../AuthContext';
import { ReportActionsBar } from './cajaReportShared';

/**
 * Cuarto reporte real de Caja — a diferencia de los demás, sin selector de período: es un
 * corte de "ahora mismo" (citas terminadas o en proceso con saldo pendiente real), no una
 * agregación de movimientos en un rango. Reusa `SpaBooking::unpaidBalance()` del lado del
 * servidor — ver la nota completa en `CashController::buildPendientesData()`.
 */
interface PendingItem {
  petName: string;
  clientName: string;
  scheduledAt: string;
  status: string;
  unpaid: number;
}
interface PendientesData {
  items: PendingItem[];
  totalPendiente: number;
  count: number;
}

function fmtMoney(n: number) {
  return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function fmtDateTime(iso: string) {
  return new Date(iso.replace(' ', 'T')).toLocaleString('es-MX', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  });
}

export function MobCajaPendientes() {
  const navigate = useNavigate();
  const crumbs = getNavCrumbs();
  const { showBreadcrumbs } = getUserPrefs();
  const { user } = useAuth();

  const [result,  setResult]  = useState<PendientesData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState<string | null>(null);

  const fetchPendientes = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch('/api/cash/reports/pendientes');
      if (!res.ok) { setError('Error al cargar el reporte.'); return; }
      setResult(await res.json());
    } catch {
      setError('No se pudo conectar con el servidor.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchPendientes(); }, []);

  return (
    <div className="min-h-screen bg-background pb-16">
      <ScreenHeader
        title="Pendientes por cobrar"
        screenTag="MobCajaPendientes"
        crumbs={crumbs}
        showBreadcrumbs={showBreadcrumbs}
        onBack={() => navigate(-1)}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); }}
      />

      <div className="mx-4 mt-4 flex flex-col gap-4">
        {loading && (
          <div className="flex justify-center py-10">
            <span className="material-symbols-outlined text-3xl text-on-surface-variant animate-spin">progress_activity</span>
          </div>
        )}

        {!loading && error && (
          <div className="bg-error-container rounded-2xl px-4 py-5 text-center">
            <span className="material-symbols-outlined text-3xl text-error mb-2 block">error</span>
            <p className="text-sm text-on-error-container">{error}</p>
            <button onClick={fetchPendientes}
              className="mt-3 px-5 py-2 rounded-xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform">
              Reintentar
            </button>
          </div>
        )}

        {!loading && !error && result && (
          <>
            <div className="bg-error/10 rounded-2xl px-4 py-3">
              <p className="text-[10px] font-semibold text-error uppercase tracking-wide mb-0.5">Total pendiente</p>
              <p className="text-xl font-bold text-error">{fmtMoney(result.totalPendiente)}</p>
              <p className="text-xs text-on-surface-variant mt-0.5">{result.count} servicio{result.count === 1 ? '' : 's'}</p>
            </div>

            <div className="bg-surface-container rounded-2xl overflow-hidden">
              {result.items.length === 0 ? (
                <div className="px-4 py-8 text-center">
                  <span className="material-symbols-outlined text-3xl text-on-surface-variant">task_alt</span>
                  <p className="text-sm text-on-surface-variant mt-2">Nada pendiente de cobro</p>
                </div>
              ) : (
                result.items.map((item, i) => (
                  <div key={i}
                    className={`px-4 py-3 ${i < result.items.length - 1 ? 'border-b border-outline-variant' : ''}`}>
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-on-surface truncate">{item.petName} · {item.clientName}</p>
                        <p className="text-xs text-on-surface-variant">
                          {fmtDateTime(item.scheduledAt)}
                          <span className="ml-1.5 text-[10px] font-semibold bg-outline-variant/40 rounded-full px-1.5 py-0.5">{item.status}</span>
                        </p>
                      </div>
                      <p className="text-sm font-bold text-error shrink-0">{fmtMoney(item.unpaid)}</p>
                    </div>
                  </div>
                ))
              )}
            </div>

            <ReportActionsBar
              pdfUrl="/api/cash/reports/pendientes/pdf"
              emailUrl="/api/cash/reports/pendientes/email"
              filename="pendientes-por-cobrar.pdf"
              defaultEmail={user?.email ?? ''}
            />
          </>
        )}
      </div>
    </div>
  );
}
