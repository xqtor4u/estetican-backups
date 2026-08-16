import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';
import { useAuth } from '../AuthContext';

/* ── Tipos ────────────────────────────────────────────────── */
interface Movement {
  id: string;
  type: string;
  direction: 'entrada' | 'salida';
  amount: number;
  concept: string;
  notes: string | null;
  account: string | null;
  branch_name: string | null;
  client_name: string | null;
  created_by: string | null;
  created_at: string;
  is_reversed: boolean;
  reversal_of_movement_id: number | string | null;
}
interface Totals {
  total_entradas: number;
  total_salidas:  number;
  count:          number;
}
interface BranchFilter {
  can_select_branch: boolean;
  selected_branch_id: number | null;
  own_branch_id: number;
}
interface ApiResult {
  movements:     Movement[];
  totals:        Totals;
  branch_filter: BranchFilter;
}
interface Branch {
  id:   number;
  name: string;
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
/** Solo estos tipos son `CashMovement` reales — cobros (`cobro_efectivo`/`cobro_banco`, que
    vienen de `Payment` o de las tablas legacy `cash_ledgers`/`bank_ledgers`) no lo son y nunca
    deben ofrecer Editar/Revertir: esos endpoints solo existen para `CashMovement`. */
const EDITABLE_TYPES = new Set(['retiro', 'deposito_banco', 'gasto', 'perdida', 'entrada']);

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
/** Fecha local en formato YYYY-MM-DD (no usar toISOString: convierte a UTC y puede correr la fecha un día según la hora del dispositivo). */
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

/** Los movimientos de esta pantalla vienen de `/api/cash/movements` con `id` prefijado
    (`"m-123"`, `"p-45"`, …) para distinguir su origen — la API de editar/revertir espera el id
    numérico real de `CashMovement`, sin el prefijo. */
function movementApiId(id: string): number {
  return parseInt(id.replace(/^m-/, ''), 10);
}

/* ── Editar movimiento — solo concepto/notas (nunca monto/tipo), mismo criterio que MobCaja ── */
function EditMovementSheet({ movement, onClose, onSaved }: {
  movement: Movement;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [concept, setConcept] = useState(movement.concept);
  const [notes, setNotes]     = useState(movement.notes ?? '');
  const [saving, setSaving]   = useState(false);
  const [error, setError]     = useState<string | null>(null);

  const canSubmit = concept.trim().length > 0 && !saving;

  const handleSubmit = async () => {
    if (!canSubmit) return;
    setSaving(true);
    setError(null);
    try {
      const res = await fetch(`/api/cash/movements/${movementApiId(movement.id)}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ concept: concept.trim(), notes: notes.trim() || null }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message ?? 'Error al guardar');
      onSaved();
    } catch (e: any) {
      setError(e.message ?? 'Error al guardar');
      setSaving(false);
    }
  };

  return (
    <>
      <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className="fixed left-0 right-0 bottom-0 z-50 bg-surface rounded-t-3xl shadow-2xl"
           style={{ maxHeight: '90vh', overflowY: 'auto' }}>
        <div className="flex justify-center pt-3 pb-1">
          <div className="w-10 h-1 rounded-full bg-outline-variant" />
        </div>
        <div className="px-4 pb-8 pt-2 flex flex-col gap-4">
          <h2 className="text-base font-semibold text-on-surface">Editar movimiento</h2>
          <p className="text-xs text-on-surface-variant">
            Solo se puede corregir el concepto y las notas — el monto ({fmtMoney(movement.amount)})
            no se edita directo. Si estaba mal, revierte el movimiento y registra uno nuevo.
          </p>
          <div className="flex flex-col gap-1">
            <label className="text-xs text-on-surface-variant font-medium">Concepto</label>
            <input type="text" value={concept} onChange={e => setConcept(e.target.value)} maxLength={255}
              className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary" />
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs text-on-surface-variant font-medium">Notas (opcional)</label>
            <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={2} maxLength={1000}
              className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary resize-none" />
          </div>
          {error && <p className="text-sm text-error bg-error/10 rounded-xl px-3 py-2">{error}</p>}
          <button onClick={handleSubmit} disabled={!canSubmit}
            className="w-full py-3.5 rounded-2xl font-semibold text-sm bg-primary text-on-primary active:scale-[0.98] transition-transform disabled:opacity-40">
            {saving ? 'Guardando…' : 'Guardar cambios'}
          </button>
        </div>
      </div>
    </>
  );
}

/* ── Revertir movimiento — nunca se borra, se cancela con uno contrario ── */
function RevertMovementSheet({ movement, onClose, onReverted }: {
  movement: Movement;
  onClose: () => void;
  onReverted: () => void;
}) {
  const [saving, setSaving] = useState(false);
  const [error, setError]   = useState<string | null>(null);

  const handleConfirm = async () => {
    setSaving(true);
    setError(null);
    try {
      const res = await fetch(`/api/cash/movements/${movementApiId(movement.id)}/revert`, { method: 'POST' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message ?? 'No se pudo revertir');
      onReverted();
    } catch (e: any) {
      setError(e.message ?? 'No se pudo revertir');
      setSaving(false);
    }
  };

  return (
    <>
      <div className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className="fixed left-0 right-0 bottom-0 z-50 bg-surface rounded-t-3xl shadow-2xl"
           style={{ maxHeight: '90vh', overflowY: 'auto' }}>
        <div className="flex justify-center pt-3 pb-1">
          <div className="w-10 h-1 rounded-full bg-outline-variant" />
        </div>
        <div className="px-4 pb-8 pt-2 flex flex-col gap-4">
          <h2 className="text-base font-semibold text-on-surface">¿Revertir este movimiento?</h2>
          <div className="bg-surface-container rounded-2xl px-4 py-3">
            <p className="text-sm font-medium text-on-surface">{movement.concept}</p>
            <p className={`text-sm font-semibold mt-1 ${movement.direction === 'entrada' ? 'text-primary' : 'text-error'}`}>
              {movement.direction === 'entrada' ? '+' : '-'}{fmtMoney(movement.amount)}
            </p>
          </div>
          <p className="text-xs text-on-surface-variant">
            Se registrará un movimiento contrario por el mismo monto para cancelar su efecto —
            este movimiento no se borra, queda marcado como revertido para mantener el historial.
          </p>
          {error && <p className="text-sm text-error bg-error/10 rounded-xl px-3 py-2">{error}</p>}
          <button onClick={handleConfirm} disabled={saving}
            className="w-full py-3.5 rounded-2xl font-semibold text-sm bg-error text-on-error active:scale-[0.98] transition-transform disabled:opacity-40">
            {saving ? 'Revirtiendo…' : 'Sí, revertir'}
          </button>
          <button onClick={onClose} disabled={saving} className="text-xs text-on-surface-variant">
            Cancelar
          </button>
        </div>
      </div>
    </>
  );
}

/* ── Fila de movimiento individual ───────────────────────── */
function MovementRow({ m, last, showBranch, canEdit, canRevert, onEdit, onRevert }: {
  m: Movement;
  last: boolean;
  showBranch: boolean;
  canEdit: boolean;
  canRevert: boolean;
  onEdit: (m: Movement) => void;
  onRevert: (m: Movement) => void;
}) {
  return (
    <div className={`flex items-start gap-3 px-4 py-3 ${last ? '' : 'border-b border-outline-variant'} ${m.is_reversed ? 'opacity-50' : ''}`}>
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-1.5 flex-wrap">
          <p className="text-sm font-medium text-on-surface truncate">
            {m.client_name ?? m.concept}
          </p>
          {m.is_reversed && (
            <span className="text-[10px] font-semibold text-on-surface-variant bg-outline-variant/40 rounded-full px-1.5 py-0.5 shrink-0">
              Revertido
            </span>
          )}
          {!!m.reversal_of_movement_id && (
            <span className="text-[10px] font-semibold text-error bg-error/10 rounded-full px-1.5 py-0.5 shrink-0">
              Reversión
            </span>
          )}
        </div>
        <p className="text-xs text-on-surface-variant">
          {m.account ?? '—'}{' · '}{fmtDateTime(m.created_at)}
          {showBranch && ' · '}
          {showBranch && (m.branch_name ?? 'Todas las sucursales')}
        </p>
        {m.notes && <p className="text-xs text-on-surface-variant/70 truncate">{m.notes}</p>}
        <p className="text-[10px] text-on-surface-variant/60 truncate">
          Registró: {m.created_by ?? 'Sin registrar'}
        </p>
        {(canEdit || canRevert) && (
          <div className="flex gap-3 mt-1">
            {canEdit && (
              <button onClick={() => onEdit(m)} className="text-[11px] text-primary font-medium">
                Editar
              </button>
            )}
            {canRevert && (
              <button onClick={() => onRevert(m)} className="text-[11px] text-error font-medium">
                Revertir
              </button>
            )}
          </div>
        )}
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
function BalanceDetailView({ movements, totals, showBranch, canEditCaja, canRevertCaja, onEdit, onRevert }: {
  movements: Movement[];
  totals: Totals;
  showBranch: boolean;
  canEditCaja: boolean;
  canRevertCaja: boolean;
  onEdit: (m: Movement) => void;
  onRevert: (m: Movement) => void;
}) {
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
                <MovementRow
                  key={m.id}
                  m={m}
                  last={i === items.length - 1}
                  showBranch={showBranch}
                  canEdit={canEditCaja && EDITABLE_TYPES.has(m.type)}
                  canRevert={canRevertCaja && EDITABLE_TYPES.has(m.type) && !m.is_reversed && !m.reversal_of_movement_id}
                  onEdit={onEdit}
                  onRevert={onRevert}
                />
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
                <MovementRow
                  key={m.id}
                  m={m}
                  last={i === items.length - 1}
                  showBranch={showBranch}
                  canEdit={canEditCaja && EDITABLE_TYPES.has(m.type)}
                  canRevert={canRevertCaja && EDITABLE_TYPES.has(m.type) && !m.is_reversed && !m.reversal_of_movement_id}
                  onEdit={onEdit}
                  onRevert={onRevert}
                />
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
  const { user } = useAuth();

  const [preset,     setPreset]     = useState<Preset>('mes');
  const [dateFrom,   setDateFrom]   = useState(startOfMonth());
  const [dateTo,     setDateTo]     = useState(today());
  const [typeFilter, setTypeFilter] = useState('');
  const [branchId,   setBranchId]   = useState<number | ''>('');
  const [branches,   setBranches]   = useState<Branch[]>([]);
  const [result,     setResult]     = useState<ApiResult | null>(null);
  const [loading,    setLoading]    = useState(false);
  const [error,      setError]      = useState<string | null>(null);
  const [editingMovement,   setEditingMovement]   = useState<Movement | null>(null);
  const [revertingMovement, setRevertingMovement] = useState<Movement | null>(null);

  const applyPreset = (p: Preset) => {
    setPreset(p);
    if (p === 'hoy')    { setDateFrom(today());        setDateTo(today()); }
    if (p === 'semana') { setDateFrom(startOfWeek());  setDateTo(today()); }
    if (p === 'mes')    { setDateFrom(startOfMonth()); setDateTo(today()); }
  };

  // El selector de sucursal solo aplica a movimientos manuales de caja — los
  // cobros (Payment/legacy) todavía no tienen branch_id, ver branch_name en
  // cada fila. Solo super-admin puede elegir sucursal (mismo criterio que
  // el Dashboard, BL-077); poblar el <select> requiere permission:ver sucursales,
  // que solo tienen los admins.
  useEffect(() => {
    if (!user?.is_admin) return;
    fetch('/api/branches')
      .then(res => res.ok ? res.json() : [])
      .then(setBranches)
      .catch(() => {});
  }, [user?.is_admin]);

  const fetchMovements = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({ date_from: dateFrom, date_to: dateTo });
      if (typeFilter)  params.set('type', typeFilter);
      if (branchId !== '') params.set('branch_id', String(branchId));
      const res = await fetch(`/api/cash/movements?${params}`);
      if (res.status === 422) { setError('No tienes una sucursal asignada — pídele a un administrador que te la asigne.'); return; }
      if (!res.ok)            { setError('Error al cargar movimientos.'); return; }
      setResult(await res.json());
    } catch {
      setError('No se pudo conectar con el servidor.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchMovements(); }, [dateFrom, dateTo, typeFilter, branchId]);

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

      {/* ── Filtro por sucursal (solo super-admin) ─────────── */}
      {result?.branch_filter.can_select_branch && (
        <div className="px-4 pb-3">
          <label className="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider block mb-1 pl-1">Sucursal</label>
          <select value={branchId} onChange={e => setBranchId(e.target.value === '' ? '' : Number(e.target.value))}
            className="w-full bg-surface-container rounded-xl px-3 py-2 text-sm text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
            <option value="">Todas las sucursales</option>
            {branches.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
          </select>
          <p className="text-[10px] text-on-surface-variant/70 mt-1 pl-1">
            Los cobros a clientes todavía no distinguen sucursal — solo los movimientos manuales de caja se filtran.
          </p>
        </div>
      )}

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
          <BalanceDetailView
            movements={result.movements}
            totals={result.totals}
            showBranch={result.branch_filter.can_select_branch && branchId === ''}
            canEditCaja={!!user?.can_edit_caja_movement}
            canRevertCaja={!!user?.can_revert_caja_movement}
            onEdit={setEditingMovement}
            onRevert={setRevertingMovement}
          />
        )}
      </div>

      {editingMovement && (
        <EditMovementSheet
          movement={editingMovement}
          onClose={() => setEditingMovement(null)}
          onSaved={() => { setEditingMovement(null); fetchMovements(); }}
        />
      )}

      {revertingMovement && (
        <RevertMovementSheet
          movement={revertingMovement}
          onClose={() => setRevertingMovement(null)}
          onReverted={() => { setRevertingMovement(null); fetchMovements(); }}
        />
      )}
    </div>
  );
}
