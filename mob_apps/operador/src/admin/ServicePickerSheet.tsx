import React, { useState } from 'react';

interface ServiceOption {
  id: number;
  name: string;
  price: number;
  duration_minutes: number | null;
}

interface ServicePickerSheetProps {
  /** Servicios ofrecibles — ya filtrados a los que no están todavía en la cita. */
  services: ServiceOption[];
  onPick: (serviceId: number) => void;
  onClose: () => void;
}

function minutesLabel(mins: number | null): string {
  if (!mins) return '';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  if (h === 0) return `${m} min`;
  if (m === 0) return `${h} h`;
  return `${h} h ${m} min`;
}

/**
 * Hoja inferior para agregar un servicio a la cita, una a la vez. Reusa el mismo patrón de
 * overlay que `PhotoSourceSheet` (`fixed inset-0 z-50`, hermano de nivel superior en el árbol,
 * nunca anidado dentro de un elemento con `transform`) — probado en dispositivo real.
 */
export function ServicePickerSheet({ services, onPick, onClose }: ServicePickerSheetProps) {
  const [q, setQ] = useState('');
  const term = q.trim().toLowerCase();
  const shown = term ? services.filter(s => s.name.toLowerCase().includes(term)) : services;

  return (
    <div className="fixed inset-0 z-50 bg-black/60 flex items-end" onClick={onClose}>
      <div
        className="w-full bg-surface rounded-t-3xl p-4 pb-8 flex flex-col gap-2 max-h-[80vh]"
        onClick={e => e.stopPropagation()}
      >
        <p className="text-center text-sm font-semibold text-on-surface-variant mb-1">Agregar servicio</p>

        {services.length > 6 && (
          <input
            autoFocus
            value={q}
            onChange={e => setQ(e.target.value)}
            placeholder="Buscar servicio…"
            className="w-full bg-surface-container border border-outline-variant rounded-2xl px-4 py-2.5 text-sm text-on-surface outline-none focus:border-primary mb-1"
          />
        )}

        <div className="flex flex-col gap-2 overflow-y-auto">
          {shown.length === 0 ? (
            <p className="text-sm text-on-surface-variant italic text-center py-6">
              {services.length === 0 ? 'Ya agregaste todos los servicios.' : 'Sin coincidencias.'}
            </p>
          ) : (
            shown.map(s => (
              <button
                key={s.id}
                onClick={() => onPick(s.id)}
                className="flex items-center gap-3 px-4 py-3 rounded-2xl bg-surface-container active:bg-surface-container-high text-left transition-colors"
              >
                <span className="material-symbols-outlined text-primary shrink-0" style={{ fontVariationSettings: "'FILL' 1" }}>
                  add_circle
                </span>
                <span className="flex-1 min-w-0">
                  <span className="block text-sm font-semibold text-on-surface truncate">{s.name}</span>
                  <span className="flex items-center gap-2 mt-0.5 text-xs text-on-surface-variant">
                    {s.price > 0 && <span>${s.price.toFixed(0)}</span>}
                    {s.duration_minutes ? (
                      <span className="flex items-center gap-1">
                        <span className="material-symbols-outlined text-xs">schedule</span>
                        {minutesLabel(s.duration_minutes)}
                      </span>
                    ) : null}
                  </span>
                </span>
              </button>
            ))
          )}
        </div>

        <button
          onClick={onClose}
          className="flex items-center justify-center px-4 py-3.5 rounded-2xl border border-outline-variant text-on-surface-variant font-medium mt-1"
        >
          Cancelar
        </button>
      </div>
    </div>
  );
}
