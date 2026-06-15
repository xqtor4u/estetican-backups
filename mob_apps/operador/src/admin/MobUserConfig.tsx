import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../AuthContext';
import { useUserPrefs } from '../hooks/useUserPrefs';

function Toggle({ value, onChange, label, description }: {
  value: boolean;
  onChange: (v: boolean) => void;
  label: string;
  description?: string;
}) {
  return (
    <button
      onClick={() => onChange(!value)}
      className="flex items-center gap-4 w-full px-4 py-3.5 text-left active:bg-surface-container-high transition-colors"
    >
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-on-surface">{label}</p>
        {description && <p className="text-xs text-on-surface-variant mt-0.5">{description}</p>}
      </div>
      <div className={`relative w-12 h-6 rounded-full transition-colors shrink-0 ${value ? 'bg-primary' : 'bg-outline'}`}>
        <span className={`absolute top-0.5 w-5 h-5 rounded-full bg-surface shadow transition-transform ${value ? 'translate-x-6' : 'translate-x-0.5'}`} />
      </div>
    </button>
  );
}

export function MobUserConfig() {
  const navigate = useNavigate();
  const { user }  = useAuth();
  const { prefs, update } = useUserPrefs();

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-20">

      {/* Header */}
      <header className="bg-surface border-b border-outline-variant flex items-center gap-3 px-4 h-14 sticky top-0 z-40">
        <button
          onClick={() => navigate(-1)}
          className="p-2 rounded-full hover:bg-surface-container-high transition-colors"
        >
          <span className="material-symbols-outlined text-on-surface">arrow_back</span>
        </button>
        <p className="font-bold text-on-surface text-base">Configuración personal</p>
      </header>

      {/* Perfil */}
      {user && (
        <div className="mx-4 mt-5 bg-surface-container border border-outline-variant rounded-2xl px-4 py-4 flex items-center gap-4">
          <div className="w-12 h-12 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
            <span className="material-symbols-outlined text-primary text-2xl" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-on-surface truncate">{user.name}</p>
            <p className="text-xs text-on-surface-variant truncate">{user.email}</p>
            {user.operator_role && (
              <p className="text-xs text-primary mt-0.5">{user.operator_role}</p>
            )}
          </div>
        </div>
      )}

      {/* Sección: Navegación */}
      <div className="mx-4 mt-5">
        <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1 px-1">Navegación</p>
        <div className="bg-surface-container border border-outline-variant rounded-2xl overflow-hidden divide-y divide-outline-variant/50">
          <Toggle
            value={prefs.showBreadcrumbs}
            onChange={v => update({ showBreadcrumbs: v })}
            label="Mostrar ruta de navegación"
            description="Muestra desde dónde llegaste en el encabezado de cada pantalla"
          />
        </div>
      </div>

      {/* Versión */}
      <p className="text-center text-xs text-on-surface-variant/40 mt-auto pt-8">EstetiCAN · App Operador</p>

    </div>
  );
}
