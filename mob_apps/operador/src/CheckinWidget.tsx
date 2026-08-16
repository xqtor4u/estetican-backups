import React, { useEffect, useState } from 'react';

/* ═══════════════════════════════════════════════════════════
   Widget de check-in — usado en el drawer de menú (App.tsx) y,
   directo, en la pantalla de Caja cuando no hay check-in activo
   (antes solo decía "sin check-in activo" sin forma de resolverlo
   ahí mismo, había que ir al menú a buscar "Registrar").
   ═══════════════════════════════════════════════════════════ */
interface CheckinStatus {
  checked_in: boolean;
  checkin_id?: number;
  branch?: { id: number; name: string };
  checked_in_at?: string;
}

export function CheckinWidget({
  onCheckedIn,
  autoExpand = false,
}: { onCheckedIn?: () => void; autoExpand?: boolean } = {}) {
  const [status,   setStatus]   = useState<CheckinStatus | null>(null);
  const [branches, setBranches] = useState<{ id: number; name: string }[]>([]);
  const [selBranch, setSelBranch] = useState<number | ''>('');
  const [expanded, setExpanded] = useState(autoExpand);
  const [busy,     setBusy]     = useState(false);

  const refresh = () => {
    fetch('/api/checkin/status').then(r => r.json()).then(setStatus).catch(() => {});
  };

  useEffect(() => {
    refresh();
    fetch('/api/branches').then(r => r.json()).then(setBranches).catch(() => {});
  }, []);

  const doCheckin = async () => {
    if (!selBranch) return;
    setBusy(true);
    await fetch('/api/checkin', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ branch_id: selBranch }),
    });
    setExpanded(false);
    setSelBranch('');
    await refresh();
    setBusy(false);
    onCheckedIn?.();
  };

  const doCheckout = async () => {
    setBusy(true);
    await fetch('/api/checkout', { method: 'POST' });
    await refresh();
    setBusy(false);
  };

  if (!status) return null;

  if (status.checked_in && status.branch) {
    const since = status.checked_in_at
      ? new Date(status.checked_in_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
      : '';
    return (
      <div className="mx-4 mb-3 bg-tertiary-container/30 border border-tertiary-fixed-dim rounded-2xl px-4 py-3">
        <div className="flex items-center gap-2">
          <span className="material-symbols-outlined text-tertiary text-lg" style={{ fontVariationSettings: "'FILL' 1" }}>location_on</span>
          <div className="flex-1 min-w-0">
            <p className="text-xs text-on-surface-variant">Check-in activo</p>
            <p className="text-sm font-semibold text-on-surface truncate">{status.branch.name}</p>
            {since && <p className="text-xs text-on-surface-variant">desde {since}</p>}
          </div>
          <button
            onClick={doCheckout}
            disabled={busy}
            className="flex items-center gap-1 px-3 py-1.5 rounded-full bg-error/10 text-error text-xs font-semibold border border-error/30 active:scale-95 transition-transform disabled:opacity-50"
          >
            <span className="material-symbols-outlined text-sm">logout</span>
            Salir
          </button>
        </div>
      </div>
    );
  }

  // No hay check-in activo
  return (
    <div className="mx-4 mb-3 bg-surface-container border border-outline-variant rounded-2xl px-4 py-3">
      {!expanded ? (
        <button
          onClick={() => setExpanded(true)}
          className="flex items-center gap-2 w-full text-left"
        >
          <span className="material-symbols-outlined text-on-surface-variant text-lg">add_location</span>
          <span className="text-sm text-on-surface-variant flex-1">Sin check-in activo</span>
          <span className="text-xs font-semibold text-primary">Registrar</span>
        </button>
      ) : (
        <div className="flex flex-col gap-2">
          <p className="text-xs font-semibold text-on-surface-variant">Selecciona sucursal</p>
          <select
            value={selBranch}
            onChange={e => setSelBranch(Number(e.target.value))}
            className="bg-background border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface outline-none focus:border-primary"
          >
            <option value="">— Sucursal —</option>
            {branches.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
          </select>
          <div className="flex gap-2">
            {!autoExpand && (
              <button
                onClick={() => { setExpanded(false); setSelBranch(''); }}
                className="flex-1 py-2 rounded-xl text-sm border border-outline-variant text-on-surface-variant active:scale-95 transition-transform"
              >
                Cancelar
              </button>
            )}
            <button
              onClick={doCheckin}
              disabled={!selBranch || busy}
              className="flex-1 py-2 rounded-xl text-sm font-semibold bg-primary text-on-primary active:scale-95 transition-transform disabled:bg-surface-container-high disabled:text-on-surface-variant"
            >
              {busy ? '…' : 'Check-in'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
