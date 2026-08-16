import React from 'react';
import { useNavigate } from 'react-router-dom';
import { setNavCrumbs } from '../navState';
import { ScreenHeader } from '../ScreenHeader';
import { useAuth } from '../AuthContext';

/**
 * Hub de Finanzas — antes "Caja" colgaba solo del menú principal, mezclando lo operativo
 * (turno del día) con lo que en el futuro sería revisión/control (reportes, cierres). Reusa
 * el mismo criterio del resto de la app para una pantalla "hub" con tarjetas de navegación
 * (mismo estilo visual que el propio menú principal, `App.tsx`).
 */
interface FinanzasItem {
  to: string;
  icon: string;
  label: string;
  description: string;
}

const ITEMS: FinanzasItem[] = [
  { to: '/caja', icon: 'point_of_sale', label: 'Caja', description: 'Turno actual, movimientos y apertura/cierre operativo.' },
  { to: '/caja/reportes', icon: 'monitoring', label: 'Reportes de caja', description: 'Resumen, métodos de pago, operadores, pendientes por cobrar.' },
];

export function MobFinanzas() {
  const navigate = useNavigate();
  const { user } = useAuth();

  const go = (to: string) => {
    setNavCrumbs([{ label: 'Finanzas', to: '/finanzas' }]);
    navigate(to);
  };

  return (
    <div className="min-h-screen bg-background pb-16">
      <ScreenHeader title="Finanzas" screenTag="MobFinanzas" noCrumbs />

      <div className="mx-4 mt-4 flex flex-col gap-2">
        {ITEMS.map(item => (
          <button
            key={item.to}
            onClick={() => go(item.to)}
            className="flex items-center gap-4 px-4 py-3.5 bg-surface-container rounded-2xl text-left active:scale-[0.98] transition-transform"
          >
            <span className="material-symbols-outlined text-2xl text-primary shrink-0">{item.icon}</span>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-on-surface">{item.label}</p>
              <p className="text-xs text-on-surface-variant">{item.description}</p>
            </div>
            <span className="material-symbols-outlined text-base text-on-surface-variant shrink-0">chevron_right</span>
          </button>
        ))}
      </div>

      {!user?.can_view_caja && (
        <p className="mx-4 mt-4 text-xs text-on-surface-variant">
          No tienes el permiso "Ver caja y reportes" — pídele a un administrador que te lo asigne
          para usar estas pantallas.
        </p>
      )}
    </div>
  );
}
