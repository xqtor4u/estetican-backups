import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';
import { useAuth } from '../AuthContext';

/* ── Tipos ────────────────────────────────────────────────── */
interface SessionInfo {
  id: number;
  register_name: string;
  branch_name: string;
  opened_by: string | null;
  opened_at: string;
  opening_amount: number;
  notes: string | null;
}
interface Totals {
  opening_amount: number;
  total_entradas: number;
  total_salidas: number;
  saldo_esperado: number;
}
interface Movement {
  id: number | string;
  type: string;
  direction: 'entrada' | 'salida';
  amount: number;
  concept: string;
  notes: string | null;
  account: string | null;
  created_at: string;
  is_reversed: boolean;
  reversal_of_movement_id: number | null;
}
interface BranchOption {
  id: number;
  name: string;
}
interface AccountOption {
  id: number;
  name: string;
}
interface MovementType {
  type: string;
  label: string;
  direction: 'entrada' | 'salida';
  icon: string;
  accounts: AccountOption[];
}

type PageState =
  | { status: 'loading' }
  | { status: 'no_branch' }
  | { status: 'select_branch'; branches: BranchOption[] }
  | { status: 'no_session'; branch: { id: number; name: string } }
  | { status: 'active'; session: SessionInfo; totals: Totals; movements: Movement[] }
  | { status: 'error'; message: string };

/* Revisión rápida por período sin salir a /caja/movimientos — reutiliza el mismo
   /api/cash/movements que esa pantalla, pero solo trae entradas/salidas/count: no hay
   "fondo inicial" ni "saldo esperado" fuera del turno abierto, esos conceptos son por sesión. */
type ViewMode = 'turno' | 'hoy' | 'semana' | 'mes' | 'rango';
const VIEW_MODES: { key: ViewMode; label: string }[] = [
  { key: 'turno',  label: 'Turno actual' },
  { key: 'hoy',    label: 'Hoy' },
  { key: 'semana', label: 'Semana' },
  { key: 'mes',    label: 'Mes' },
  { key: 'rango',  label: 'Rango' },
];
interface PeriodMovement {
  id: number | string;
  type: string;
  direction: 'entrada' | 'salida';
  amount: number;
  concept: string;
  notes: string | null;
  account: string | null;
  created_at: string;
  is_reversed: boolean;
  reversal_of_movement_id: number | null;
}
interface PeriodTotals {
  total_entradas: number;
  total_salidas: number;
  count: number;
}

/* ── Helpers ─────────────────────────────────────────────── */
/** Los movimientos del turno (`/api/cash/session`) traen `id` numérico real, pero los de la
    vista por período (`/api/cash/movements`) vienen con prefijo (`"m-123"`, `"p-45"`, …) para
    distinguir su origen (CashMovement/Payment/legacy) — la API de editar/revertir espera el id
    numérico real de `CashMovement`, sin el prefijo. */
function movementApiId(id: number | string): number {
  return typeof id === 'number' ? id : parseInt(String(id).replace(/^m-/, ''), 10);
}
function fmtMoney(n: number) {
  return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function fmtTime(iso: string) {
  return new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
}
function fmtDateTime(iso: string) {
  return new Date(iso).toLocaleString('es-MX', {
    hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short',
  });
}
/** Fecha local en formato YYYY-MM-DD (no usar toISOString: convierte a UTC y puede correr la
    fecha un día según la hora del dispositivo) — mismo criterio que MobCajaMovimientos. */
function toDateStr(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function todayStr()       { return toDateStr(new Date()); }
function startOfWeekStr() {
  const d = new Date(), day = d.getDay();
  d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
  return toDateStr(d);
}
function startOfMonthStr() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
}

const TYPE_LABELS: Record<string, string> = {
  retiro: 'Retiro',
  deposito_banco: 'Depósito banco',
  gasto: 'Gasto',
  perdida: 'Pérdida',
  entrada: 'Entrada',
};
/** Solo estos tipos son `CashMovement` reales — cobros (`cobro_efectivo`/`cobro_banco`, que
    vienen de `Payment` o de las tablas legacy `cash_ledgers`/`bank_ledgers`) no lo son y nunca
    deben ofrecer Editar/Revertir: esos endpoints solo existen para `CashMovement`. */
const EDITABLE_TYPES = new Set(Object.keys(TYPE_LABELS));
/** Solo para mostrar texto — a diferencia de EDITABLE_TYPES, sí incluye los tipos de cobro. */
const DISPLAY_LABELS: Record<string, string> = {
  ...TYPE_LABELS,
  cobro_efectivo: 'Cobro efectivo',
  cobro_banco: 'Cobro banco/tarjeta',
};

/* ── Formulario — bottom sheet ───────────────────────────── */
interface FormSheetProps {
  sessionId: number;
  movementTypes: MovementType[];
  onClose: () => void;
  onSaved: (m: Movement) => void;
}

function MovementFormSheet({ sessionId, movementTypes, onClose, onSaved }: FormSheetProps) {
  const [selType, setSelType]       = useState<MovementType | null>(null);
  const [accountId, setAccountId]   = useState<number | ''>('');
  const [amount, setAmount]         = useState('');
  const [concept, setConcept]       = useState('');
  const [notes, setNotes]           = useState('');
  const [saving, setSaving]         = useState(false);
  const [error, setError]           = useState<string | null>(null);
  const amountRef = useRef<HTMLInputElement>(null);

  const handleTypeSelect = (mt: MovementType) => {
    setSelType(mt);
    setAccountId(mt.accounts.length === 1 ? mt.accounts[0].id : '');
    setError(null);
    setTimeout(() => amountRef.current?.focus(), 100);
  };

  const canSubmit =
    selType !== null &&
    accountId !== '' &&
    parseFloat(amount) > 0 &&
    concept.trim().length > 0 &&
    !saving;

  const handleSubmit = async () => {
    if (!canSubmit || !selType) return;
    setSaving(true);
    setError(null);
    try {
      const res = await fetch(`/api/cash/sessions/${sessionId}/movements`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          type: selType.type,
          amount: parseFloat(amount),
          concept: concept.trim(),
          notes: notes.trim() || null,
          counterpart_account_id: accountId,
        }),
      });
      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.message ?? 'Error al guardar');
      }
      const saved: Movement = await res.json();
      onSaved(saved);
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
        {/* Asa */}
        <div className="flex justify-center pt-3 pb-1">
          <div className="w-10 h-1 rounded-full bg-outline-variant" />
        </div>

        <div className="px-4 pb-8 pt-2 flex flex-col gap-4">
          <h2 className="text-base font-semibold text-on-surface">Nuevo movimiento</h2>

          {/* Selector de tipo */}
          {!selType ? (
            <div className="flex flex-col gap-2">
              <p className="text-xs text-on-surface-variant font-medium">Tipo de movimiento</p>
              {movementTypes.map(mt => (
                <button
                  key={mt.type}
                  onClick={() => handleTypeSelect(mt)}
                  className="flex items-center gap-3 px-4 py-3.5 bg-surface-container rounded-2xl text-left active:scale-[0.98] transition-transform"
                >
                  <span
                    className="material-symbols-outlined text-2xl"
                    style={{ color: mt.direction === 'entrada' ? 'var(--color-primary)' : 'var(--color-error)' }}
                  >
                    {mt.icon}
                  </span>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-on-surface">{mt.label}</p>
                    <p className="text-xs text-on-surface-variant capitalize">{mt.direction}</p>
                  </div>
                  <span className="material-symbols-outlined text-base text-on-surface-variant">chevron_right</span>
                </button>
              ))}
            </div>
          ) : (
            <>
              {/* Tipo seleccionado — chip para cambiar */}
              <button
                onClick={() => setSelType(null)}
                className="self-start flex items-center gap-2 px-3 py-1.5 rounded-full border border-outline-variant text-sm text-on-surface active:bg-surface-container-high"
              >
                <span
                  className="material-symbols-outlined text-base"
                  style={{ color: selType.direction === 'entrada' ? 'var(--color-primary)' : 'var(--color-error)' }}
                >
                  {selType.icon}
                </span>
                {selType.label}
                <span className="material-symbols-outlined text-sm text-on-surface-variant">close</span>
              </button>

              {/* Cuenta contable */}
              {selType.accounts.length > 1 && (
                <div className="flex flex-col gap-1">
                  <label className="text-xs text-on-surface-variant font-medium">Cuenta contable</label>
                  <select
                    value={accountId}
                    onChange={e => setAccountId(Number(e.target.value))}
                    className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary"
                  >
                    <option value="">— Seleccionar cuenta —</option>
                    {selType.accounts.map(a => (
                      <option key={a.id} value={a.id}>{a.name}</option>
                    ))}
                  </select>
                </div>
              )}
              {selType.accounts.length === 1 && (
                <p className="text-xs text-on-surface-variant">
                  Cuenta: <span className="font-medium text-on-surface">{selType.accounts[0].name}</span>
                </p>
              )}

              {/* Monto */}
              <div className="flex flex-col gap-1">
                <label className="text-xs text-on-surface-variant font-medium">Monto</label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-on-surface-variant font-medium">$</span>
                  <input
                    ref={amountRef}
                    type="number"
                    inputMode="decimal"
                    min="0.01"
                    step="0.01"
                    value={amount}
                    onChange={e => setAmount(e.target.value)}
                    placeholder="0.00"
                    className="w-full bg-surface-container border border-outline-variant rounded-xl pl-7 pr-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary"
                  />
                </div>
              </div>

              {/* Concepto */}
              <div className="flex flex-col gap-1">
                <label className="text-xs text-on-surface-variant font-medium">Concepto</label>
                <input
                  type="text"
                  value={concept}
                  onChange={e => setConcept(e.target.value)}
                  placeholder="Ej. Compra de insumos"
                  maxLength={255}
                  className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary"
                />
              </div>

              {/* Notas (opcional) */}
              <div className="flex flex-col gap-1">
                <label className="text-xs text-on-surface-variant font-medium">Notas (opcional)</label>
                <textarea
                  value={notes}
                  onChange={e => setNotes(e.target.value)}
                  rows={2}
                  maxLength={1000}
                  className="bg-surface-container border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary resize-none"
                />
              </div>

              {error && (
                <p className="text-sm text-error bg-error/10 rounded-xl px-3 py-2">{error}</p>
              )}

              <button
                onClick={handleSubmit}
                disabled={!canSubmit}
                className="w-full py-3.5 rounded-2xl font-semibold text-sm bg-primary text-on-primary active:scale-[0.98] transition-transform disabled:opacity-40"
              >
                {saving ? 'Guardando…' : 'Registrar movimiento'}
              </button>
            </>
          )}
        </div>
      </div>
    </>
  );
}

/* ── Editar movimiento — solo concepto/notas (nunca monto/tipo) ─────────
   Cada movimiento ya tiene un asiento contable real detrás; cambiar el monto o el tipo sin
   tocar ese asiento rompería la contabilidad. Si el monto estaba mal, el flujo correcto es
   revertir y registrar uno nuevo, no editar este. */
function EditMovementSheet({ movement, onClose, onSaved }: {
  movement: Movement | PeriodMovement;
  onClose: () => void;
  onSaved: (m: Movement | PeriodMovement) => void;
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
      const data = await res.json();
      if (!res.ok) throw new Error(data.message ?? 'Error al guardar');
      onSaved(data);
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
  movement: Movement | PeriodMovement;
  onClose: () => void;
  onReverted: (reversal: Movement | PeriodMovement, originalId: number | string) => void;
}) {
  const [saving, setSaving] = useState(false);
  const [error, setError]   = useState<string | null>(null);

  const handleConfirm = async () => {
    setSaving(true);
    setError(null);
    try {
      const res = await fetch(`/api/cash/movements/${movementApiId(movement.id)}/revert`, { method: 'POST' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message ?? 'No se pudo revertir');
      onReverted(data, movement.id);
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

/* ══════════════════════════════════════════════════════════ */
export function MobCaja() {
  const navigate = useNavigate();
  const crumbs = getNavCrumbs();
  const { showBreadcrumbs } = getUserPrefs();
  const { user } = useAuth();

  const [page, setPage]               = useState<PageState>({ status: 'loading' });
  const [movTypes, setMovTypes]       = useState<MovementType[]>([]);
  const [showForm, setShowForm]       = useState(false);
  const [refreshing, setRefreshing]   = useState(false);
  const [editingMovement, setEditingMovement]     = useState<Movement | PeriodMovement | null>(null);
  const [revertingMovement, setRevertingMovement] = useState<Movement | PeriodMovement | null>(null);

  // Sucursal para Caja — nunca viene del check-in (eso es solo asistencia/RH, sin relación con
  // autorización). Un operador normal siempre usa su `branch_id` asignado del lado del servidor;
  // esto solo importa para super-admin, que puede elegir cualquier sucursal explícitamente.
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(() => {
    const saved = localStorage.getItem('caja_branch_id');
    return saved ? Number(saved) : null;
  });
  const branchQuery = selectedBranchId ? `branch_id=${selectedBranchId}` : '';

  // Abrir caja directo desde el móvil — antes esta pantalla solo decía "Abre la sesión desde
  // el backoffice de finanzas", sin ninguna forma de resolverlo acá.
  const [openingAmount, setOpeningAmount] = useState('');
  const [openingNotes,  setOpeningNotes]  = useState('');
  const [opening,       setOpening]       = useState(false);
  const [openError,     setOpenError]     = useState<string | null>(null);

  // Revisión rápida por período (Hoy/Semana/Mes/Rango) sin salir a /caja/movimientos.
  const [viewMode,        setViewMode]        = useState<ViewMode>('turno');
  const [rangeFrom,       setRangeFrom]       = useState(startOfMonthStr());
  const [rangeTo,         setRangeTo]         = useState(todayStr());
  const [periodMovements, setPeriodMovements] = useState<PeriodMovement[]>([]);
  const [periodTotals,    setPeriodTotals]    = useState<PeriodTotals | null>(null);
  const [periodLoading,   setPeriodLoading]   = useState(false);
  const [periodError,     setPeriodError]     = useState<string | null>(null);

  const loadSession = async (quiet = false) => {
    if (!quiet) setPage({ status: 'loading' });
    else setRefreshing(true);
    try {
      const res = await fetch(`/api/cash/session${branchQuery ? '?' + branchQuery : ''}`);
      if (res.status === 403) {
        setPage({ status: 'error', message: 'No tienes permiso para ver Caja. Pídele a un administrador que te asigne el permiso "Ver caja y reportes".' });
        return;
      }
      if (!res.ok) {
        setPage({ status: 'error', message: 'Error al cargar la caja.' });
        return;
      }
      const data = await res.json();
      setPage(data as PageState);
    } catch {
      setPage({ status: 'error', message: 'No se pudo conectar con el servidor.' });
    } finally {
      setRefreshing(false);
    }
  };

  const selectBranch = (id: number) => {
    localStorage.setItem('caja_branch_id', String(id));
    setSelectedBranchId(id);
  };

  const changeBranch = () => {
    localStorage.removeItem('caja_branch_id');
    setSelectedBranchId(null);
  };

  const openCashSession = async () => {
    const amount = parseFloat(openingAmount);
    if (isNaN(amount) || amount < 0) return;
    setOpening(true);
    setOpenError(null);
    try {
      const res = await fetch('/api/cash/session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          opening_amount: amount,
          notes: openingNotes.trim() || null,
          branch_id: selectedBranchId,
        }),
      });
      const data = await res.json();
      if (!res.ok) {
        setOpenError(data.message ?? 'No se pudo abrir la caja.');
        setOpening(false);
        return;
      }
      setPage(data as PageState);
    } catch {
      setOpenError('No se pudo conectar con el servidor.');
    } finally {
      setOpening(false);
    }
  };

  const loadMovementTypes = async () => {
    try {
      const res = await fetch('/api/cash/movement-types');
      const data: MovementType[] = await res.json();
      setMovTypes(data);
    } catch {
      // silencioso — si falla, el form mostrará lista vacía
    }
  };

  useEffect(() => {
    loadMovementTypes();
  }, []);

  useEffect(() => {
    loadSession();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedBranchId]);

  const fetchPeriod = async () => {
    let from: string, to: string;
    if (viewMode === 'hoy')         { from = todayStr();       to = todayStr(); }
    else if (viewMode === 'semana') { from = startOfWeekStr(); to = todayStr(); }
    else if (viewMode === 'mes')    { from = startOfMonthStr(); to = todayStr(); }
    else if (viewMode === 'rango')  { from = rangeFrom;         to = rangeTo; }
    else return;

    setPeriodLoading(true);
    setPeriodError(null);
    try {
      const params = `date_from=${from}&date_to=${to}${branchQuery ? '&' + branchQuery : ''}`;
      const res = await fetch(`/api/cash/movements?${params}`);
      if (!res.ok) { setPeriodError('No se pudo cargar el período.'); return; }
      const data = await res.json();
      setPeriodMovements(data.movements);
      setPeriodTotals(data.totals);
    } catch {
      setPeriodError('No se pudo conectar con el servidor.');
    } finally {
      setPeriodLoading(false);
    }
  };

  useEffect(() => {
    if (page.status !== 'active' || viewMode === 'turno') return;
    fetchPeriod();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page.status, viewMode, rangeFrom, rangeTo, selectedBranchId]);

  const handleSaved = (m: Movement) => {
    setShowForm(false);
    if (viewMode !== 'turno') fetchPeriod();
    // Actualizar estado local sin refetch completo
    setPage(prev => {
      if (prev.status !== 'active') return prev;
      const movements = [m, ...prev.movements];
      const totalEntradas = movements.filter(x => x.direction === 'entrada').reduce((s, x) => s + x.amount, 0);
      const totalSalidas  = movements.filter(x => x.direction === 'salida').reduce((s, x) => s + x.amount, 0);
      return {
        ...prev,
        movements,
        totals: {
          opening_amount: prev.totals.opening_amount,
          total_entradas: Math.round(totalEntradas * 100) / 100,
          total_salidas:  Math.round(totalSalidas  * 100) / 100,
          saldo_esperado: Math.round((prev.totals.opening_amount + totalEntradas - totalSalidas) * 100) / 100,
        },
      };
    });
  };

  const handleMovementEdited = (updated: Movement | PeriodMovement) => {
    setEditingMovement(null);
    setPage(prev => {
      if (prev.status !== 'active') return prev;
      return { ...prev, movements: prev.movements.map(m => m.id === updated.id ? { ...m, ...updated } : m) };
    });
    // periodMovements trae ids con prefijo ("m-123"), la respuesta del backend no — comparar
    // por el id numérico real (ver `movementApiId`), no por igualdad directa.
    setPeriodMovements(prev => prev.map(m => movementApiId(m.id) === movementApiId(updated.id) ? { ...m, ...updated, id: m.id } : m));
  };

  const handleMovementReverted = (reversal: Movement | PeriodMovement, originalId: number | string) => {
    setRevertingMovement(null);
    if (viewMode !== 'turno') {
      // La reversión (y el efecto en los totales) vive en el reporte de período — más simple
      // refrescarlo entero que reconstruir a mano el mismo cálculo que ya hace el backend.
      fetchPeriod();
    }
    setPage(prev => {
      if (prev.status !== 'active') return prev;
      const movements = prev.movements.map(m => m.id === originalId ? { ...m, is_reversed: true } : m);
      const withReversal = viewMode === 'turno' ? [reversal as Movement, ...movements] : movements;
      const totalEntradas = withReversal.filter(x => x.direction === 'entrada').reduce((s, x) => s + x.amount, 0);
      const totalSalidas  = withReversal.filter(x => x.direction === 'salida').reduce((s, x) => s + x.amount, 0);
      return {
        ...prev,
        movements: withReversal,
        totals: {
          opening_amount: prev.totals.opening_amount,
          total_entradas: Math.round(totalEntradas * 100) / 100,
          total_salidas:  Math.round(totalSalidas  * 100) / 100,
          saldo_esperado: Math.round((prev.totals.opening_amount + totalEntradas - totalSalidas) * 100) / 100,
        },
      };
    });
  };

  /* ── Pantallas de estado no-activo ─────────────────────── */
  const headerProps = {
    title: 'Caja',
    screenTag: 'MobCaja' as const,
    crumbs,
    showBreadcrumbs,
    onCrumbClick: (to: string, prev: typeof crumbs) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); },
  };

  if (page.status === 'loading') {
    return (
      <div className="min-h-screen bg-background pb-16">
        <ScreenHeader {...headerProps} />
        <div className="flex items-center justify-center pt-24">
          <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">
            progress_activity
          </span>
        </div>
      </div>
    );
  }

  if (page.status === 'error') {
    return (
      <div className="min-h-screen bg-background pb-16">
        <ScreenHeader {...headerProps} />
        <div className="flex flex-col items-center gap-4 px-6 pt-16 text-center">
          <span className="material-symbols-outlined text-5xl text-error">error</span>
          <p className="text-sm text-on-surface-variant">{page.message}</p>
          <button
            onClick={() => loadSession()}
            className="px-6 py-2.5 rounded-2xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform"
          >
            Reintentar
          </button>
        </div>
      </div>
    );
  }

  if (page.status === 'no_branch') {
    return (
      <div className="min-h-screen bg-background pb-16">
        <ScreenHeader {...headerProps} />
        <div className="flex flex-col items-center gap-4 px-6 pt-16 text-center">
          <span className="material-symbols-outlined text-5xl text-on-surface-variant">business</span>
          <h2 className="text-base font-semibold text-on-surface">Sin sucursal asignada</h2>
          {/* Esto es distinto del check-in (asistencia del día) — la sucursal es un dato de
              organización que asigna un administrador, no algo que el operador resuelva solo
              acá. Por eso no hay ninguna acción de autoservicio en esta pantalla. */}
          <p className="text-sm text-on-surface-variant">
            No tienes una sucursal asignada todavía. Pídele a un administrador que te la asigne
            desde tu perfil para poder usar Caja.
          </p>
          <button onClick={() => loadSession()}
            className="mt-1 px-6 py-2.5 rounded-2xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform">
            Reintentar
          </button>
        </div>
      </div>
    );
  }

  if (page.status === 'select_branch') {
    return (
      <div className="min-h-screen bg-background pb-16">
        <ScreenHeader {...headerProps} />
        <div className="flex flex-col items-center gap-2 px-6 pt-8 pb-4 text-center">
          <span className="material-symbols-outlined text-4xl text-on-surface-variant">point_of_sale</span>
          <h2 className="text-base font-semibold text-on-surface">Elige una sucursal</h2>
          <p className="text-sm text-on-surface-variant">
            Como administrador puedes ver la caja de cualquier sucursal — elige con cuál trabajar.
          </p>
        </div>
        <div className="mx-4 flex flex-col gap-2">
          {page.branches.map(b => (
            <button key={b.id} onClick={() => selectBranch(b.id)}
              className="flex items-center justify-between px-4 py-3.5 bg-surface-container rounded-2xl text-left active:scale-[0.98] transition-transform">
              <span className="text-sm font-medium text-on-surface">{b.name}</span>
              <span className="material-symbols-outlined text-base text-on-surface-variant">chevron_right</span>
            </button>
          ))}
        </div>
      </div>
    );
  }

  if (page.status === 'no_session') {
    return (
      <div className="min-h-screen bg-background pb-16">
        <ScreenHeader {...headerProps} subtitle={page.branch.name} />
        {user?.is_admin && (
          <button onClick={changeBranch} className="mx-6 mt-2 text-xs text-primary font-medium">
            Cambiar sucursal
          </button>
        )}
        <div className="flex flex-col items-center gap-3 px-6 pt-10 text-center">
          <span className="material-symbols-outlined text-5xl text-on-surface-variant">point_of_sale</span>
          {/* "Sesión" en esta app también significa sesión de LOGIN ("Cerrar sesión" en el
              menú) — "Sin sesión activa" a secas, sin leer el párrafo de abajo, se lee como
              "no estás logueado". Mismo tipo de término sobrecargado que ya se aclaró en el
              cobro (SYNC-020, la palabra "Caja") — acá se saca "sesión" del título. */}
          <h2 className="text-base font-semibold text-on-surface">Sin caja abierta</h2>
          <p className="text-sm text-on-surface-variant">
            No hay ninguna caja abierta en <strong>{page.branch.name}</strong>. Ábrela para
            empezar a registrar cobros y movimientos.
          </p>
        </div>

        {/* Antes esta pantalla solo decía "Abre la sesión desde el backoffice de finanzas" —
            sin ninguna forma de resolverlo acá, había que salir a una computadora. Solo se
            muestra el formulario si el usuario tiene el permiso real de abrir caja — si no,
            no tiene sentido ofrecerle una acción que el backend igual va a rechazar. */}
        {user?.can_open_caja ? (
        <div className="mx-4 mt-4 bg-surface-container rounded-2xl px-4 py-4 flex flex-col gap-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-semibold text-on-surface-variant">Fondo inicial</label>
            <input
              type="number"
              inputMode="decimal"
              min="0"
              step="0.01"
              value={openingAmount}
              onChange={e => setOpeningAmount(e.target.value)}
              placeholder="0.00"
              className="bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-base text-on-surface outline-none focus:border-primary"
            />
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-semibold text-on-surface-variant">Notas (opcional)</label>
            <input
              type="text"
              value={openingNotes}
              onChange={e => setOpeningNotes(e.target.value)}
              placeholder="Ej. turno de la mañana"
              className="bg-background border border-outline-variant rounded-xl px-3 py-2.5 text-sm text-on-surface outline-none focus:border-primary"
            />
          </div>
          {openError && (
            <p className="text-xs text-error">{openError}</p>
          )}
          <button
            onClick={openCashSession}
            disabled={opening || openingAmount.trim() === '' || isNaN(parseFloat(openingAmount))}
            className="mt-1 py-3 rounded-2xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform disabled:opacity-40"
          >
            {opening ? 'Abriendo…' : 'Abrir caja'}
          </button>
          <button
            onClick={() => loadSession()}
            className="text-xs text-on-surface-variant"
          >
            Reintentar (por si ya la abrieron desde otro lado)
          </button>
        </div>
        ) : (
          <div className="mx-4 mt-4 text-center">
            <p className="text-xs text-on-surface-variant">
              No tienes permiso para abrir caja — pídele a alguien con ese permiso que lo haga.
            </p>
            <button onClick={() => loadSession()} className="mt-2 text-xs text-primary font-medium">
              Reintentar
            </button>
          </div>
        )}
      </div>
    );
  }

  /* ── Sesión activa ──────────────────────────────────────── */
  const { session, totals, movements } = page;

  return (
    <div className="min-h-screen bg-background pb-24">
      <ScreenHeader {...headerProps} subtitle={`${session.register_name} · ${session.branch_name}`} />

      {/* Card de sesión */}
      <div className="mx-4 mt-2 mb-4 bg-surface-container rounded-2xl px-4 py-4">
        <div className="flex items-start gap-3">
          <span className="material-symbols-outlined text-2xl text-primary mt-0.5"
                style={{ fontVariationSettings: "'FILL' 1" }}>
            point_of_sale
          </span>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-on-surface">{session.register_name}</p>
            <p className="text-xs text-on-surface-variant">{session.branch_name}</p>
            <p className="text-xs text-on-surface-variant">
              Abierta a las {fmtTime(session.opened_at)}
              {session.opened_by ? ` · ${session.opened_by}` : ''}
            </p>
          </div>
          <button
            onClick={() => loadSession(true)}
            disabled={refreshing}
            className="p-1.5 rounded-full text-on-surface-variant active:bg-surface-container-high transition-colors disabled:opacity-40"
          >
            <span className={`material-symbols-outlined text-lg ${refreshing ? 'animate-spin' : ''}`}>
              refresh
            </span>
          </button>
        </div>
        {session.notes && (
          <p className="mt-2 text-xs text-on-surface-variant bg-background rounded-xl px-3 py-2">
            {session.notes}
          </p>
        )}
        {user?.is_admin && (
          <button onClick={changeBranch} className="mt-2 text-xs text-primary font-medium">
            Cambiar sucursal
          </button>
        )}
      </div>

      {/* Selector de período — para revisar rápido sin salir a /caja/movimientos */}
      <div className="flex gap-2 px-4 pb-2 overflow-x-auto scrollbar-none">
        {VIEW_MODES.map(v => (
          <button key={v.key} onClick={() => setViewMode(v.key)}
            className={`shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors ${
              viewMode === v.key ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant'
            }`}>
            {v.label}
          </button>
        ))}
      </div>

      {viewMode === 'rango' && (
        <div className="flex gap-2 px-4 pb-3">
          <div className="flex-1">
            <label className="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider block mb-1 pl-1">Desde</label>
            <input type="date" value={rangeFrom} max={rangeTo}
              onChange={e => setRangeFrom(e.target.value)}
              className="w-full bg-surface-container rounded-xl px-3 py-2 text-sm text-on-surface border border-outline-variant focus:outline-none focus:border-primary" />
          </div>
          <div className="flex-1">
            <label className="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider block mb-1 pl-1">Hasta</label>
            <input type="date" value={rangeTo} min={rangeFrom} max={todayStr()}
              onChange={e => setRangeTo(e.target.value)}
              className="w-full bg-surface-container rounded-xl px-3 py-2 text-sm text-on-surface border border-outline-variant focus:outline-none focus:border-primary" />
          </div>
        </div>
      )}

      {/* Resumen de totales */}
      {viewMode === 'turno' ? (
        <div className="mx-4 mb-4 grid grid-cols-2 gap-3">
          <div className="bg-surface-container rounded-2xl px-4 py-3">
            <p className="text-xs text-on-surface-variant mb-1">Fondo inicial</p>
            <p className="text-base font-bold text-on-surface">{fmtMoney(totals.opening_amount)}</p>
          </div>
          <div className="bg-surface-container rounded-2xl px-4 py-3">
            <p className="text-xs text-on-surface-variant mb-1">Saldo esperado</p>
            <p className="text-base font-bold text-primary">{fmtMoney(totals.saldo_esperado)}</p>
          </div>
          <div className="bg-surface-container rounded-2xl px-4 py-3">
            <p className="text-xs text-on-surface-variant mb-1">Entradas</p>
            <p className="text-base font-semibold text-primary">+{fmtMoney(totals.total_entradas)}</p>
          </div>
          <div className="bg-surface-container rounded-2xl px-4 py-3">
            <p className="text-xs text-on-surface-variant mb-1">Salidas</p>
            <p className="text-base font-semibold text-error">-{fmtMoney(totals.total_salidas)}</p>
          </div>
        </div>
      ) : periodLoading ? (
        <div className="flex justify-center py-8">
          <span className="material-symbols-outlined text-3xl text-on-surface-variant animate-spin">progress_activity</span>
        </div>
      ) : periodError ? (
        <div className="mx-4 mb-4 bg-error-container rounded-2xl px-4 py-4 text-center">
          <p className="text-sm text-on-error-container">{periodError}</p>
          <button onClick={fetchPeriod}
            className="mt-2 px-5 py-1.5 rounded-xl bg-primary text-on-primary text-sm font-semibold active:scale-95 transition-transform">
            Reintentar
          </button>
        </div>
      ) : periodTotals && (
        <div className="mx-4 mb-4 grid grid-cols-3 gap-2">
          <div className="bg-primary/10 rounded-2xl px-3 py-3">
            <p className="text-[10px] font-semibold text-primary uppercase tracking-wide mb-0.5">Entradas</p>
            <p className="text-sm font-bold text-primary">+{fmtMoney(periodTotals.total_entradas)}</p>
          </div>
          <div className="bg-error/10 rounded-2xl px-3 py-3">
            <p className="text-[10px] font-semibold text-error uppercase tracking-wide mb-0.5">Salidas</p>
            <p className="text-sm font-bold text-error">-{fmtMoney(periodTotals.total_salidas)}</p>
          </div>
          {(() => {
            const neto = periodTotals.total_entradas - periodTotals.total_salidas;
            return (
              <div className={`${neto >= 0 ? 'bg-primary/5' : 'bg-error/5'} rounded-2xl px-3 py-3`}>
                <p className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wide mb-0.5">Neto</p>
                <p className={`text-sm font-bold ${neto >= 0 ? 'text-primary' : 'text-error'}`}>
                  {neto >= 0 ? '+' : ''}{fmtMoney(neto)}
                </p>
              </div>
            );
          })()}
        </div>
      )}

      {/* Lista de movimientos */}
      {!periodLoading && !periodError && (() => {
        const list: (Movement | PeriodMovement)[] = viewMode === 'turno' ? movements : periodMovements;
        return (
          <div className="mx-4 mb-4">
            <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-widest mb-2 px-1">
              {viewMode === 'turno' ? `Movimientos (${list.length})` : `Movimientos del período (${list.length})`}
            </p>

            {list.length === 0 ? (
              <div className="bg-surface-container rounded-2xl px-4 py-6 text-center">
                <span className="material-symbols-outlined text-3xl text-on-surface-variant">receipt_long</span>
                <p className="text-sm text-on-surface-variant mt-2">
                  {viewMode === 'turno' ? 'Sin movimientos en esta sesión' : 'Sin movimientos para este período'}
                </p>
              </div>
            ) : (
              <div className="bg-surface-container rounded-2xl overflow-hidden">
                {list.map((m, i) => {
                  const isManualMovement = EDITABLE_TYPES.has(m.type);
                  const canEdit   = isManualMovement && !!user?.can_edit_caja_movement;
                  const canRevert = isManualMovement && !!user?.can_revert_caja_movement && !m.is_reversed && !m.reversal_of_movement_id;
                  return (
                  <div
                    key={m.id}
                    className={`flex items-start gap-3 px-4 py-3 ${i < list.length - 1 ? 'border-b border-outline-variant' : ''} ${m.is_reversed ? 'opacity-50' : ''}`}
                  >
                    <div className={`w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5 ${
                      m.direction === 'entrada' ? 'bg-primary/10' : 'bg-error/10'
                    }`}>
                      <span
                        className="material-symbols-outlined text-lg"
                        style={{ color: m.direction === 'entrada' ? 'var(--color-primary)' : 'var(--color-error)' }}
                      >
                        {m.direction === 'entrada' ? 'add' : 'remove'}
                      </span>
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-1.5 flex-wrap">
                        <p className="text-sm font-medium text-on-surface truncate">{m.concept}</p>
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
                        {DISPLAY_LABELS[m.type] ?? m.type}
                        {m.account ? ` · ${m.account}` : ''}
                      </p>
                      <p className="text-xs text-on-surface-variant">{fmtDateTime(m.created_at)}</p>
                      {(canEdit || canRevert) && (
                        <div className="flex gap-3 mt-1">
                          {canEdit && (
                            <button onClick={() => setEditingMovement(m)} className="text-[11px] text-primary font-medium">
                              Editar
                            </button>
                          )}
                          {canRevert && (
                            <button onClick={() => setRevertingMovement(m)} className="text-[11px] text-error font-medium">
                              Revertir
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                    <p className={`text-sm font-semibold shrink-0 ${
                      m.direction === 'entrada' ? 'text-primary' : 'text-error'
                    }`}>
                      {m.direction === 'entrada' ? '+' : '-'}{fmtMoney(m.amount)}
                    </p>
                  </div>
                  );
                })}
              </div>
            )}
          </div>
        );
      })()}

      {/* Enlace a historial */}
      <div className="mx-4 mb-4">
        <button
          onClick={() => {
            setNavCrumbs([{ label: 'Caja', to: '/caja' }]);
            navigate('/caja/movimientos');
          }}
          className="w-full flex items-center justify-center gap-2 py-3 rounded-2xl border border-outline-variant text-sm text-on-surface-variant active:bg-surface-container transition-colors"
        >
          <span className="material-symbols-outlined text-lg">history</span>
          Ver historial de movimientos
        </button>
      </div>

      {/* FAB */}
      {user?.can_create_caja_movement && (
        <button
          onClick={() => setShowForm(true)}
          className="fixed bottom-20 right-4 z-30 w-14 h-14 rounded-full bg-primary text-on-primary shadow-lg flex items-center justify-center active:scale-95 transition-transform"
        >
          <span className="material-symbols-outlined text-2xl">add</span>
        </button>
      )}

      {/* Bottom sheet formulario */}
      {showForm && movTypes.length > 0 && (
        <MovementFormSheet
          sessionId={session.id}
          movementTypes={movTypes}
          onClose={() => setShowForm(false)}
          onSaved={handleSaved}
        />
      )}

      {editingMovement && (
        <EditMovementSheet
          movement={editingMovement}
          onClose={() => setEditingMovement(null)}
          onSaved={handleMovementEdited}
        />
      )}

      {revertingMovement && (
        <RevertMovementSheet
          movement={revertingMovement}
          onClose={() => setRevertingMovement(null)}
          onReverted={handleMovementReverted}
        />
      )}
    </div>
  );
}
