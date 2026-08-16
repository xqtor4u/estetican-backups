import React from 'react';
import { useNavigate } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';

/**
 * Los 5 reportes que Tomas recomendó para el primer corte móvil, todos construidos — el resto
 * (por sucursal, ventas por servicio, cancelaciones/ajustes, cortes históricos) quedó
 * explícitamente para backoffice, no para esta pantalla.
 */
const REPORTS = [
  {
    to: '/caja/reportes/resumen',
    icon: 'summarize',
    label: 'Resumen de caja',
    description: 'Total cobrado, entradas, salidas y neto — por período, con desglose por tipo.',
  },
  {
    to: '/caja/reportes/metodos-pago',
    icon: 'payments',
    label: 'Métodos de pago',
    description: 'Efectivo, tarjeta, transferencia — desglose de cobros por método.',
  },
  {
    to: '/caja/reportes/por-operador',
    icon: 'badge',
    label: 'Por operador',
    description: 'Quién registró cada movimiento, entradas y salidas por separado.',
  },
  {
    to: '/caja/reportes/pendientes',
    icon: 'pending_actions',
    label: 'Pendientes por cobrar',
    description: 'Servicios terminados o en proceso todavía sin pagar — corte en vivo.',
  },
  {
    to: '/caja/reportes/cierres',
    icon: 'fact_check',
    label: 'Cierre de turno',
    description: 'Fondo inicial, efectivo esperado, contado y diferencia de cada cierre real.',
  },
];

export function MobCajaReportes() {
  const navigate = useNavigate();
  const crumbs = getNavCrumbs();
  const { showBreadcrumbs } = getUserPrefs();

  const goToReport = (to: string) => {
    setNavCrumbs([{ label: 'Reportes de caja', to: '/caja/reportes' }]);
    navigate(to);
  };

  return (
    <div className="min-h-screen bg-background pb-16">
      <ScreenHeader
        title="Reportes de caja"
        screenTag="MobCajaReportes"
        crumbs={crumbs}
        showBreadcrumbs={showBreadcrumbs}
        onBack={() => navigate(-1)}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); }}
      />

      <div className="mx-4 mt-4 flex flex-col gap-2">
        {REPORTS.map(r => (
          <button
            key={r.to}
            onClick={() => goToReport(r.to)}
            className="flex items-center gap-4 px-4 py-3.5 bg-surface-container rounded-2xl text-left active:scale-[0.98] transition-transform"
          >
            <span className="material-symbols-outlined text-2xl text-primary shrink-0">{r.icon}</span>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-on-surface">{r.label}</p>
              <p className="text-xs text-on-surface-variant">{r.description}</p>
            </div>
            <span className="material-symbols-outlined text-base text-on-surface-variant shrink-0">chevron_right</span>
          </button>
        ))}
      </div>

      <div className="mx-4 mt-4 bg-surface-container rounded-2xl px-4 py-4 text-center">
        <span className="material-symbols-outlined text-3xl text-on-surface-variant">business_center</span>
        <p className="text-sm font-semibold text-on-surface mt-2">Más reportes en el backoffice</p>
        <p className="text-xs text-on-surface-variant mt-1">
          Por sucursal, ventas por servicio, cancelaciones/ajustes y cortes históricos viven en
          el backoffice web, no acá.
        </p>
      </div>
    </div>
  );
}
