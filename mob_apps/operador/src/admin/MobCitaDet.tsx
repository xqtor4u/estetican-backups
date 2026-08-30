import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useSortable, SortBtn } from '../hooks/useSortable';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { ScreenHeader } from '../ScreenHeader';
import { useSiblingNav, setSiblingNav } from '../hooks/useSiblingNav';
import { useAuth } from '../AuthContext';

/** Debe coincidir exactamente con el mensaje de OperatorAvailabilityChecker::isOutsideWorkingHours en el backend. */
const SCHEDULE_OVERRIDE_MESSAGE = 'El operador seleccionado no labora en el horario indicado.';

/* ── Tipos ────────────────────────────────────────────────── */
interface BookingDetail {
  id: number;
  order_folio: string | null;
  scheduled_at: string;
  time: string;
  end_time: string | null;
  duration_minutes: number | null;
  status: string;
  notes: string | null;
  cancellation_reason: string | null;
  total: number;
  pet: { id: number; name: string; species: string | null; breed: string | null; photo: string | null };
  client: { id: number; name: string } | null;
  services: {
    id: number | null;
    booking_service_id: number;
    name: string;
    type: string | null;
    price: number;
    duration_minutes: number | null;
    offset_minutes: number;
    start_time: string;
    operator_id: number | null;
    operator_name: string | null;
    is_external: boolean;
    external_cost: number | null;
    started_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    not_performed_at: string | null;
    not_performed_reason: string | null;
  }[];
  operator: { id: number; name: string; photo_url: string | null } | null;
}

/** Estado efectivo de una línea de servicio (misma cascada que el accessor del backend). */
type LineState = 'cancelled' | 'not_performed' | 'completed' | 'in_progress' | 'pending';
function lineStateOf(l: { cancelled_at: string | null; not_performed_at: string | null; completed_at: string | null; started_at: string | null }): LineState {
  if (l.cancelled_at) return 'cancelled';
  if (l.not_performed_at) return 'not_performed';
  if (l.completed_at) return 'completed';
  if (l.started_at) return 'in_progress';
  return 'pending';
}
type BookingServiceLine = BookingDetail['services'][number];
interface ServiceCat { id: number; name: string; type: string | null; price: number; duration_minutes: number | null }
interface OperatorCat { id: number; name: string; role: string | null; photo_url: string | null }
interface OccBooking  { id: number; time: string }
interface BookingPayment { id: number; amount: number; payment_method: string; category: string; destination: string; created_at: string }
interface ProcessNote { id: number; note: string; author: string | null; created_at: string; updated_at: string }
interface TxRow {
  key: string;
  tipo: string;
  descripcion: string;
  detalle: string;
  monto: number;
  fecha_iso: string;
}

/* ── Status UI ───────────────────────────────────────────── */
const STATUS_LABEL: Record<string, string> = {
  scheduled:     'Programada',
  work_order:    'En proceso',
  completed:     'Completada',
  cancelled:     'Cancelada',
  no_show:       'No se presentó',
  unfulfillable: 'No realizada',
};
const STATUS_COLOR: Record<string, string> = {
  scheduled:     'bg-primary-container text-on-primary-container border-primary-fixed-dim',
  work_order:    'bg-secondary-container text-on-secondary-container border-secondary-fixed',
  completed:     'bg-tertiary-container text-on-tertiary-container border-tertiary-fixed-dim',
  cancelled:     'bg-error-container text-on-error-container border-error',
  no_show:       'bg-error-container text-on-error-container border-error',
  // Ámbar sólido a propósito (no un token del tema, ninguno es ámbar): mismo
  // color que ya usamos en semana/mes — distinto de no_show (no es falta del
  // cliente) y distinto de work_order/verde (no es un servicio normal en curso).
  unfulfillable: 'bg-amber-500 text-white border-amber-500',
};
const STATUS_BAR: Record<string, string> = {
  scheduled:     'bg-primary',
  work_order:    'bg-secondary',
  completed:     'bg-tertiary',
  cancelled:     'bg-error',
  no_show:       'bg-error',
  unfulfillable: 'bg-amber-500',
};
// 'cobro' no es un status real — es la acción de navegar a MobCobro.
// 'realizada' tampoco — marca la cita como llevada a cabo tal como se programó,
// sin importar la hora real de inicio/fin, y lleva directo a cobro.
const NEXT_STATUSES: Record<string, { value: string; label: string; icon: string; danger?: boolean; cobro?: boolean; realizada?: boolean; unfulfilled?: boolean }[]> = {
  scheduled:  [
    { value: 'work_order',    label: 'Iniciar cita', icon: 'play_arrow' },
    { value: 'realizada',     label: 'Realizada',        icon: 'task_alt',    realizada: true },
    { value: 'unfulfillable', label: 'No se realizó',    icon: 'person_off', danger: true, unfulfilled: true },
    { value: 'cancelled',     label: 'Cancelar cita',    icon: 'cancel',     danger: true },
  ],
  work_order: [
    { value: 'cobro',         label: 'Completar y cobrar', icon: 'point_of_sale', cobro: true },
    { value: 'realizada',     label: 'Realizada',          icon: 'task_alt',      realizada: true },
    { value: 'unfulfillable', label: 'No se pudo completar', icon: 'report', danger: true, unfulfilled: true },
    { value: 'cancelled',     label: 'Cancelar',           icon: 'cancel', danger: true },
  ],
};

/* ── Helpers ─────────────────────────────────────────────── */
const STEP = 30;
const START_H = 9;
const END_H   = 19;

function buildSlots(): string[] {
  const slots: string[] = [];
  let mins = START_H * 60;
  while (mins < END_H * 60) {
    slots.push(`${String(Math.floor(mins / 60)).padStart(2, '0')}:${String(mins % 60).padStart(2, '0')}`);
    mins += STEP;
  }
  return slots;
}
const ALL_SLOTS = buildSlots();

function localDateStr(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function addDays(d: Date, n: number): Date {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
}
function fmtChip(d: Date, offset: number): string {
  if (offset === 0) return 'Hoy';
  if (offset === 1) return 'Mañana';
  return d.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric' });
}
function fmtLong(d: Date): string {
  return d.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}
function minutesToHHMM(m: number): string {
  const h = Math.floor(m / 60), min = m % 60;
  if (h === 0) return `${min} min`;
  if (min === 0) return `${h} h`;
  return `${h} h ${min} min`;
}
function slotAddMins(slot: string, mins: number): string {
  const [h, m] = slot.split(':').map(Number);
  const total = h * 60 + m + mins;
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}
function parseDateLocal(datetimeStr: string): Date {
  // "2026-06-14 10:00:00" → Date local (sin conversión UTC)
  const [date, time] = datetimeStr.split(' ');
  const [y, mo, d] = date.split('-').map(Number);
  const [hh, mm]   = (time ?? '00:00').split(':').map(Number);
  return new Date(y, mo - 1, d, hh, mm);
}

/* ══════════════════════════════════════════════════════════ */
export function MobCitaDet() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();

  /* Breadcrumbs del módulo navState — más confiable que location.state */
  const crumbs = getNavCrumbs();
  const showBreadcrumbs = getUserPrefs().showBreadcrumbs;

  /* Toast de éxito */
  const [toast, setToast] = useState<string | null>(null);
  const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const detDateInputRef = useRef<HTMLInputElement>(null);
  const showToast = (msg: string) => {
    if (toastTimer.current) clearTimeout(toastTimer.current);
    setToast(msg);
    toastTimer.current = setTimeout(() => setToast(null), 3000);
  };
  const today = useMemo(() => new Date(), []);

  /* Datos remotos */
  const [booking,   setBooking]   = useState<BookingDetail | null>(null);
  const [catalog,   setCatalog]   = useState<ServiceCat[]>([]);
  const [operators, setOperators] = useState<OperatorCat[]>([]);
  const [loading,   setLoading]   = useState(true);

  /* Modo edición */
  const [editing,   setEditing]   = useState(false);

  /* Navegación ‹ Cita #N › + arrastre lateral entre citas del mismo día (patrón común de las
     pantallas de detalle — ver useSiblingNav). La lista llega de la Agenda; si se entró por
     deep-link, el efecto de abajo la rellena con la agenda del día de la cita. */
  const sib = useSiblingNav(id, 'cita');

  /* Campos editables */
  const [selDate,    setSelDate]    = useState<Date>(new Date());
  const [selSlot,    setSelSlot]    = useState<string>('');
  const [selSvcs,    setSelSvcs]    = useState<number[]>([]);
  const [selOp,      setSelOp]      = useState<number | null>(null);
  const [customDur,  setCustomDur]  = useState<number | null>(null);
  const [notes,      setNotes]      = useState('');
  const [occupied,   setOccupied]   = useState<Set<string>>(new Set());
  const [loadSlots,  setLoadSlots]  = useState(false);

  /* Cancelación */
  const [cancelReason, setCancelReason] = useState('');
  const [showCancel,   setShowCancel]   = useState(false);

  /* No se realizó — no_show (falta del cliente) o unfulfillable (cualquier otro motivo, no punitivo) */
  const [showUnfulfilled,   setShowUnfulfilled]   = useState(false);
  const [unfulfilledType,   setUnfulfilledType]   = useState<'no_show' | 'unfulfillable'>('no_show');
  const [unfulfilledNote,   setUnfulfilledNote]   = useState('');

  /* Tolerancia de inicio */
  const [graceMinutes, setGraceMinutes] = useState(15);
  const [graceDialog,  setGraceDialog]  = useState<{ diffMinutes: number } | null>(null);
  // Checklist al iniciar una cita con 2+ servicios — a diferencia de graceDialog (solo
  // confirma horario), acá se elige cuál(es) servicio(s) arrancan ahora y cuál(es) quedan
  // pendientes. Al llegar acá la cita siempre sigue "scheduled" (el botón que lo abre solo
  // existe en ese estado), así que ninguna línea pudo haber arrancado todavía.
  const [startChecklist, setStartChecklist] = useState<{ diffMinutes: number | null; selected: Set<number> } | null>(null);
  const [startingChecklist, setStartingChecklist] = useState(false);
  const [startingLineId, setStartingLineId] = useState<number | null>(null); // booking_service_id de la línea que se está iniciando sola, desde "Servicio / Operador"
  const [completingLineId, setCompletingLineId] = useState<number | null>(null); // ídem, para "Terminar"
  const [lineActionId, setLineActionId] = useState<number | null>(null); // línea con un PATCH en curso (no realizó / cancelar / reactivar)
  const [voidPanel, setVoidPanel] = useState<{ lineId: number; kind: 'not_performed' | 'cancelled' } | null>(null);
  const [voidReason, setVoidReason] = useState('');

  /* Pagos registrados */
  const [payments,     setPayments]     = useState<BookingPayment[]>([]);
  const [totalPaid,    setTotalPaid]    = useState(0);

  /* Notas de proceso (tomadas mientras el servicio está "En proceso") */
  const [processNotes,   setProcessNotes]   = useState<ProcessNote[]>([]);
  const [showNoteModal,  setShowNoteModal]  = useState(false);
  const [noteText,       setNoteText]       = useState('');
  const [savingNote,     setSavingNote]     = useState(false);
  const [noteErr,        setNoteErr]        = useState<string | null>(null);

  /* Asignar profesional / costo externo por línea de servicio (solo en "En proceso") */
  const [assigningLine,   setAssigningLine]   = useState<BookingServiceLine | null>(null);
  const [assignOperator,  setAssignOperator]  = useState<number | ''>('');
  const [assignExternal,  setAssignExternal]  = useState(false);
  const [assignOrigCost,  setAssignOrigCost]  = useState<number | null>(null);
  const [assignCost,      setAssignCost]      = useState('');
  const [assignBasePrice, setAssignBasePrice] = useState(0);
  const [assignPrice,     setAssignPrice]     = useState('');
  const [savingAssign,    setSavingAssign]    = useState(false);
  const [assignErr,       setAssignErr]       = useState<string | null>(null);

  /* Edición rápida de "Notas de la cita" (sin entrar al modo edición completo) */
  const [editingBookingNote, setEditingBookingNote] = useState(false);
  const [bookingNoteText,    setBookingNoteText]    = useState('');
  const [savingBookingNote,  setSavingBookingNote]  = useState(false);

  /* Edición de notas de proceso ya existentes */
  const [editingProcessNoteId, setEditingProcessNoteId] = useState<number | null>(null);
  const [editingProcessText,   setEditingProcessText]   = useState('');
  const [savingProcessNoteId,  setSavingProcessNoteId]  = useState<number | null>(null);

  /* Movimientos unificados (servicios + cobros) */
  const txRows = useMemo<TxRow[]>(() => {
    if (!booking) return [];
    // Mientras "Servicio / Operador" ya muestra nombre + precio + operador por línea
    // (scheduled/work_order), repetir los servicios acá sería duplicar la misma información —
    // Movimientos se limita a los cobros. En el resto de los estados (completada, cancelada,
    // etc.) esa sección no se muestra, así que acá sigue siendo la única fuente.
    const showsServiceOperatorSection =
      (booking.status === 'scheduled' || booking.status === 'work_order') && booking.services.length > 0;
    const svcRows: TxRow[] = showsServiceOperatorSection ? [] : booking.services.map((s, i) => ({
      key:        `svc-${i}`,
      tipo:       'Servicio',
      descripcion: s.name,
      detalle:    s.duration_minutes ? minutesToHHMM(s.duration_minutes) : '',
      monto:      s.price,
      fecha_iso:  booking.scheduled_at,
    }));
    const pmtRows: TxRow[] = payments.map(p => ({
      key:        `pmt-${p.id}`,
      tipo:       p.category === 'liquidacion' ? 'Liquidación' : 'Anticipo',
      descripcion: p.payment_method,
      detalle:    p.destination === 'caja' ? 'Caja' : 'Banco',
      monto:      p.amount,
      fecha_iso:  p.created_at,
    }));
    return [...svcRows, ...pmtRows];
  }, [booking, payments]);

  const { sortKey: txSortKey, direction: txDir, toggle: txToggle, sorted: sortedTxRows } =
    useSortable<TxRow>(txRows, 'fecha_iso');

  /* Acciones de toda la cita — cuando ya hay líneas de servicio en el acordeón, se recorta lo
     que quedó duplicado: "Iniciar cita" (se inicia por línea) y "Realizada" en work_order
     (redundante con "Completar y cobrar"). Sin líneas, la lista completa como antes. */
  const acciones = useMemo(() => {
    const base = booking ? (NEXT_STATUSES[booking.status] ?? []) : [];
    if (!booking || booking.services.length === 0) return base;
    return base.filter(a =>
      a.value !== 'work_order' && !(a.realizada && booking.status === 'work_order')
    );
  }, [booking]);

  /* Guardado / error de carga */
  const [saving,    setSaving]    = useState(false);
  const [saveErr,   setSaveErr]   = useState<string | null>(null);
  const [offerOverride, setOfferOverride] = useState(false);
  const [loadErr,   setLoadErr]   = useState<string | null>(null);

  /* ── Carga inicial ─────────────────────────────────────── */
  useEffect(() => {
    if (!id) return;

    const load = async () => {
      // Transición limpia al navegar entre citas hermanas (‹ ›): sin esto se quedaba la
      // cita anterior en pantalla mientras carga, y si la nueva fallaba (p. ej. una cita
      // ajena heredada de otro usuario) el render mezclaba datos viejos con el error.
      setLoading(true);
      setLoadErr(null);
      setBooking(null);
      try {
        const [bRes, svcsRes, opsRes, settingsRes] = await Promise.all([
          fetch(`/api/bookings/${id}`),
          fetch('/api/services'),
          fetch('/api/operators'),
          fetch('/api/settings/booking'),
        ]);

        if (!bRes.ok) {
          const err = await bRes.json().catch(() => ({}));
          setLoadErr(bRes.status === 404
            ? 'Esta cita no existe o no tienes acceso a ella.'
            : (err.message ?? `Error ${bRes.status}`));
          return;
        }

        // `/api/services` y `/api/operators` responden 403 a un operador restringido (sin
        // "ver mascotas/operadores"); antes se hacía `.json()` a ciegas y el body de error
        // ({message:…}) terminaba en `setOperators`/`setCatalog` → `operators.map` reventaba y
        // la pantalla quedaba en negro. Ahora: si no es 200 o no es arreglo, queda vacío.
        const [b, svcsRaw, opsRaw, settingsData]: [BookingDetail, unknown, unknown, { grace_minutes: number }] = await Promise.all([
          bRes.json(),
          svcsRes.ok ? svcsRes.json().catch(() => []) : Promise.resolve([]),
          opsRes.ok ? opsRes.json().catch(() => []) : Promise.resolve([]),
          settingsRes.ok ? settingsRes.json().catch(() => ({ grace_minutes: 15 })) : Promise.resolve({ grace_minutes: 15 }),
        ]);
        const svcs: ServiceCat[]  = Array.isArray(svcsRaw) ? svcsRaw : [];
        const ops:  OperatorCat[] = Array.isArray(opsRaw)  ? opsRaw  : [];
        if (settingsData?.grace_minutes != null) setGraceMinutes(settingsData.grace_minutes);

        setBooking(b);
        setCatalog(svcs);
        setOperators(ops);

        const dt = parseDateLocal(b.scheduled_at);
        setSelDate(new Date(dt.getFullYear(), dt.getMonth(), dt.getDate()));
        setSelSlot(b.time.slice(0, 5));
        setSelSvcs(b.services.map(s => s.id).filter(Boolean) as number[]);
        setSelOp(b.operator?.id ?? null);
        setCustomDur(b.duration_minutes ?? null);
        setNotes(b.notes ?? '');
      } catch (e) {
        setLoadErr('No se pudo conectar con el servidor.');
      } finally {
        setLoading(false);
      }
    };

    load();
  }, [id]);

  /* ── Fallback de hermanas: si no llegó lista de la Agenda (deep-link), se pide la agenda
     del día de la cita — el día contiene por definición a esta cita. ── */
  useEffect(() => {
    if (!booking || sib.hasNav) return;
    const dateStr = booking.scheduled_at.slice(0, 10); // "YYYY-MM-DD HH:MM:SS" → "YYYY-MM-DD"
    fetch(`/api/agenda?date=${dateStr}`)
      .then(r => (r.ok ? r.json() : []))
      .then((rows: unknown) => {
        if (!Array.isArray(rows)) return;
        const ids = (rows as { id: number; time: string }[])
          .slice()
          .sort((a, b) => (a.time || '').localeCompare(b.time || ''))
          .map(r => r.id);
        if (ids.length) setSiblingNav('cita', ids, x => `/citas/${x}`);
      })
      .catch(() => {});
  }, [booking?.id, booking?.scheduled_at, sib.hasNav]); // eslint-disable-line react-hooks/exhaustive-deps

  /* ── Carga pagos ──────────────────────────────────────── */
  useEffect(() => {
    if (!booking) return;
    fetch(`/api/bookings/${booking.id}/payments`)
      .then(r => r.ok ? r.json() : null)
      .then((data: { payments: BookingPayment[]; paid: number } | null) => {
        if (data) { setPayments(data.payments); setTotalPaid(data.paid); }
      })
      .catch(() => {});
  }, [booking]);

  /* ── Carga notas de proceso ───────────────────────────── */
  useEffect(() => {
    if (!booking) return;
    fetch(`/api/bookings/${booking.id}/process-notes`)
      .then(r => r.ok ? r.json() : null)
      .then((data: ProcessNote[] | null) => { if (data) setProcessNotes(data); })
      .catch(() => {});
  }, [booking?.id]);

  /* ── Guardar nota de proceso ──────────────────────────── */
  const saveNote = async () => {
    if (!booking || !noteText.trim()) return;
    setSavingNote(true);
    setNoteErr(null);
    try {
      const res = await fetch(`/api/bookings/${booking.id}/process-notes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ note: noteText.trim() }),
      });
      const data = await res.json();
      if (!res.ok) { setNoteErr(data.message ?? 'Error'); setSavingNote(false); return; }
      setProcessNotes(prev => [...prev, data]);
      setNoteText('');
      setShowNoteModal(false);
      showToast('Nota agregada');
    } catch { setNoteErr('No se pudo conectar con el servidor.'); }
    setSavingNote(false);
  };

  /* ── Asignar profesional / costo externo a una línea de servicio ── */
  const openAssign = (line: BookingServiceLine) => {
    setAssigningLine(line);
    setAssignOperator(line.operator_id ?? '');
    setAssignExternal(line.is_external);
    setAssignOrigCost(line.external_cost);
    setAssignCost(line.external_cost !== null ? String(line.external_cost) : '');
    setAssignBasePrice(line.price);
    setAssignPrice(String(line.price));
    setAssignErr(null);
  };
  const closeAssign = () => setAssigningLine(null);

  const assignSuggestedPrice = useMemo(() => {
    const cost = parseFloat(assignCost);
    if (!assignOrigCost || assignOrigCost <= 0 || !Number.isFinite(cost) || cost === assignOrigCost) return null;
    return (assignBasePrice * (cost / assignOrigCost)).toFixed(2);
  }, [assignCost, assignOrigCost, assignBasePrice]);

  const saveAssign = async () => {
    if (!booking || !assigningLine || !assignOperator) return;
    setSavingAssign(true);
    setAssignErr(null);
    try {
      const res = await fetch(`/api/bookings/${booking.id}/services/${assigningLine.booking_service_id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          operator_id: assignOperator,
          is_external: assignExternal,
          external_cost: assignExternal && assignCost.trim() !== '' ? parseFloat(assignCost) : null,
          current_price: assignPrice.trim() !== '' ? parseFloat(assignPrice) : undefined,
        }),
      });
      const data = await res.json();
      if (!res.ok) { setAssignErr(data.message ?? 'Error'); setSavingAssign(false); return; }
      setBooking(data);
      setAssigningLine(null);
      showToast('Profesional asignado');
    } catch { setAssignErr('No se pudo conectar con el servidor.'); }
    setSavingAssign(false);
  };

  /* ── Iniciar una línea de servicio suelta (botón "Iniciar" en "Servicio / Operador") ── */
  const startSingleLine = async (bookingServiceId: number) => {
    if (!booking) return;
    setStartingLineId(bookingServiceId);
    try {
      const res = await fetch(`/api/bookings/${booking.id}/services/${bookingServiceId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mark_started: true }),
      });
      const data = await res.json();
      if (!res.ok) { setSaveErr(data.message ?? 'Error'); setStartingLineId(null); return; }
      setBooking(data);
      showToast('Servicio iniciado');
    } catch { setSaveErr('No se pudo conectar con el servidor.'); }
    setStartingLineId(null);
  };

  /* ── Terminar una línea de servicio suelta (botón "Terminar" en "Servicio / Operador") —
     independiente de "Completar y cobrar", que sigue cerrando toda la cita de un jalón. ── */
  const completeSingleLine = async (bookingServiceId: number) => {
    if (!booking) return;
    setCompletingLineId(bookingServiceId);
    try {
      const res = await fetch(`/api/bookings/${booking.id}/services/${bookingServiceId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mark_completed: true }),
      });
      const data = await res.json();
      if (!res.ok) { setSaveErr(data.message ?? 'Error'); setCompletingLineId(null); return; }
      setBooking(data);
      showToast('Servicio terminado');
    } catch { setSaveErr('No se pudo conectar con el servidor.'); }
    setCompletingLineId(null);
  };

  /* ── "No se realizó" / "Cancelar" / "Reactivar" una línea suelta ──
     Mismo endpoint que iniciar/terminar; una línea excluida sale del total a cobrar (backend). */
  const patchLine = async (bookingServiceId: number, body: Record<string, unknown>, okToast: string) => {
    if (!booking) return;
    setLineActionId(bookingServiceId);
    try {
      const res = await fetch(`/api/bookings/${booking.id}/services/${bookingServiceId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok) { setSaveErr(data.message ?? 'Error'); setLineActionId(null); return; }
      setBooking(data);
      showToast(okToast);
    } catch { setSaveErr('No se pudo conectar con el servidor.'); }
    setLineActionId(null);
  };

  const confirmVoidLine = () => {
    if (!voidPanel) return;
    const reason = voidReason.trim();
    const body = voidPanel.kind === 'not_performed'
      ? { mark_not_performed: true, not_performed_reason: reason || null }
      : { mark_cancelled: true, cancellation_reason: reason || null };
    patchLine(voidPanel.lineId, body, voidPanel.kind === 'not_performed' ? 'Servicio marcado como no realizado' : 'Servicio cancelado');
    setVoidPanel(null);
    setVoidReason('');
  };

  const reactivateLine = (bookingServiceId: number) =>
    patchLine(bookingServiceId, { mark_reactivate: true }, 'Servicio reactivado');

  const realizadaSingleLine = (bookingServiceId: number) =>
    patchLine(bookingServiceId, { mark_realizada: true }, 'Servicio marcado como realizado');

  /* ── Confirmar el checklist de "Iniciar cita" con 2+ servicios — un PATCH por línea marcada ── */
  const confirmStartChecklist = async () => {
    if (!booking || !startChecklist || startChecklist.selected.size === 0) return;
    setStartingChecklist(true);
    setSaveErr(null);
    try {
      let lastData: BookingDetail | null = null;
      for (const bookingServiceId of startChecklist.selected) {
        const res = await fetch(`/api/bookings/${booking.id}/services/${bookingServiceId}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ mark_started: true }),
        });
        const data = await res.json();
        if (!res.ok) { setSaveErr(data.message ?? 'Error'); setStartingChecklist(false); return; }
        lastData = data;
      }
      if (lastData) setBooking(lastData);
      setStartChecklist(null);
      showToast('Cita iniciada');
    } catch { setSaveErr('No se pudo conectar con el servidor.'); }
    setStartingChecklist(false);
  };

  /* ── Editar "Notas de la cita" (rápido, sin modo edición completo) ── */
  const startEditBookingNote = () => { setBookingNoteText(booking?.notes ?? ''); setEditingBookingNote(true); };
  const cancelEditBookingNote = () => { setEditingBookingNote(false); setBookingNoteText(''); };
  const saveBookingNote = async () => {
    if (!booking) return;
    setSavingBookingNote(true);
    try {
      const res = await fetch(`/api/bookings/${booking.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notes: bookingNoteText.trim() || null }),
      });
      const data = await res.json();
      if (res.ok) {
        setBooking(data);
        setNotes(data.notes ?? '');
        setEditingBookingNote(false);
        showToast('Nota de la cita actualizada');
      }
    } catch { /* silencioso */ }
    setSavingBookingNote(false);
  };

  /* ── Editar una nota de proceso ya existente ──────────── */
  const startEditProcessNote = (n: ProcessNote) => { setEditingProcessNoteId(n.id); setEditingProcessText(n.note); };
  const cancelEditProcessNote = () => { setEditingProcessNoteId(null); setEditingProcessText(''); };
  const saveEditProcessNote = async () => {
    if (!booking || editingProcessNoteId == null || !editingProcessText.trim()) return;
    setSavingProcessNoteId(editingProcessNoteId);
    try {
      const res = await fetch(`/api/bookings/${booking.id}/process-notes/${editingProcessNoteId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ note: editingProcessText.trim() }),
      });
      const data = await res.json();
      if (res.ok) {
        setProcessNotes(prev => prev.map(n => n.id === editingProcessNoteId ? data : n));
        setEditingProcessNoteId(null);
        setEditingProcessText('');
      }
    } catch { /* silencioso */ }
    setSavingProcessNoteId(null);
  };

  /* ── Carga slots ocupados ──────────────────────────────── */
  const loadOccupied = useCallback((date: Date, excludeId: number) => {
    setLoadSlots(true);
    fetch(`/api/agenda?date=${localDateStr(date)}`)
      .then(r => r.json())
      .then((bookings: OccBooking[]) =>
        setOccupied(new Set(
          bookings.filter(b => b.id !== excludeId).map(b => b.time.slice(0, 5))
        ))
      )
      .catch(() => setOccupied(new Set()))
      .finally(() => setLoadSlots(false));
  }, []);

  useEffect(() => {
    if (editing && booking && booking.status === 'scheduled') loadOccupied(selDate, booking.id);
  }, [editing, selDate, booking, loadOccupied]);

  /* ── Duración ──────────────────────────────────────────── */
  const catalogDuration = useMemo(() =>
    catalog.filter(s => selSvcs.includes(s.id))
           .reduce((acc, s) => acc + (s.duration_minutes ?? STEP), 0),
    [catalog, selSvcs]
  );
  const effectiveDuration = customDur ?? (catalogDuration > 0 ? catalogDuration : STEP);
  const slotsNeeded       = Math.max(1, Math.ceil(effectiveDuration / STEP));
  const endTime           = selSlot ? slotAddMins(selSlot, effectiveDuration) : null;

  useEffect(() => { setCustomDur(null); }, [selSvcs]);

  const DUR_STEP = 15;
  const adjustDur = (delta: number) =>
    setCustomDur(Math.min(480, Math.max(15, effectiveDuration + delta)));

  /* ── Slot validity ─────────────────────────────────────── */
  const selSlotIdx   = selSlot ? ALL_SLOTS.indexOf(selSlot) : -1;
  const isInRange    = (slot: string) => {
    const idx = ALL_SLOTS.indexOf(slot);
    return selSlotIdx >= 0 && idx > selSlotIdx && idx < selSlotIdx + slotsNeeded;
  };
  const isSlotInvalid = (slot: string) => {
    const idx = ALL_SLOTS.indexOf(slot);
    for (let i = 0; i < slotsNeeded; i++) {
      const s = ALL_SLOTS[idx + i];
      if (!s || occupied.has(s)) return true;
    }
    return false;
  };

  /* ── Cambio de estado (sin edición) ───────────────────── */
  const changeStatus = async (newStatus: string, reason?: string) => {
    if (!booking) return;
    setSaving(true);
    setSaveErr(null);
    try {
      const body: Record<string, unknown> = { status: newStatus };
      if (reason?.trim()) body.cancellation_reason = reason.trim();

      const res = await fetch(`/api/bookings/${booking.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok) { setSaveErr(data.message ?? 'Error'); setSaving(false); return; }
      setBooking(data);
      setShowCancel(false);      setCancelReason('');
      setShowUnfulfilled(false); setUnfulfilledNote('');
      showToast(STATUS_LABEL[newStatus] ? `Cita marcada como: ${STATUS_LABEL[newStatus]}` : 'Estado actualizado');
    } catch { setSaveErr('No se pudo conectar con el servidor.'); }
    setSaving(false);
  };

  /* ── "Realizada": marca la cita como llevada a cabo tal como se programó,
     sin la fricción de horario de "Iniciar cita". Si aún no se había
     iniciado, primero pasa a work_order en silencio y luego va a cobro. ── */
  const markRealizada = async () => {
    if (!booking) return;
    const goToCobro = () => {
      setNavCrumbs([{ label: `Cita #${booking.id}`, to: `/citas/${booking.id}` }]);
      navigate(`/citas/${booking.id}/cobro`);
    };

    if (booking.status === 'work_order') { goToCobro(); return; }

    setSaving(true);
    setSaveErr(null);
    try {
      const res = await fetch(`/api/bookings/${booking.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: 'work_order' }),
      });
      const data = await res.json();
      if (!res.ok) { setSaveErr(data.message ?? 'Error'); setSaving(false); return; }
      setSaving(false);
      goToCobro();
    } catch { setSaveErr('No se pudo conectar con el servidor.'); setSaving(false); }
  };

  /* ── Guardar edición ───────────────────────────────────── */
  const saveEdit = async (overrideAvailability = false) => {
    if (!booking) return;
    setSaving(true);
    setSaveErr(null);
    setOfferOverride(false);
    try {
      const res = await fetch(`/api/bookings/${booking.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          operator_id:      selOp,
          // Una cita ya iniciada no se reprograma (mismo límite que el resto del
          // sistema) — mandar scheduled_at aquí para un work_order lo rechaza el API.
          ...(booking.status === 'scheduled' ? { scheduled_at: `${localDateStr(selDate)} ${selSlot}:00` } : {}),
          duration_minutes: effectiveDuration,
          services:         selSvcs,
          notes:            notes.trim() || null,
          ...(overrideAvailability ? { override_availability: true } : {}),
        }),
      });
      const data = await res.json();
      if (!res.ok) {
        const msg = data.errors
          ? Object.values(data.errors as Record<string, string[]>).flat().join(' · ')
          : (data.message ?? `Error ${res.status}`);
        setSaveErr(msg);
        setOfferOverride(msg === SCHEDULE_OVERRIDE_MESSAGE && !!user?.can_override_schedule);
        setSaving(false); return;
      }
      setBooking(data);
      // Resincronizar campos
      const dt = parseDateLocal(data.scheduled_at);
      setSelDate(new Date(dt.getFullYear(), dt.getMonth(), dt.getDate()));
      setSelSlot(data.time.slice(0, 5));
      setSelSvcs(data.services.map((s: any) => s.id).filter(Boolean));
      setSelOp(data.operator?.id ?? null);
      setCustomDur(data.duration_minutes ?? null);
      setNotes(data.notes ?? '');
      setEditing(false);
      showToast('Cambios guardados');
    } catch { setSaveErr('No se pudo conectar con el servidor.'); }
    setSaving(false);
  };

  /* ── Render ────────────────────────────────────────────── */
  if (loading) return (
    <div className="min-h-screen bg-background flex items-center justify-center">
      <span className="material-symbols-outlined text-4xl text-on-surface-variant animate-spin">progress_activity</span>
    </div>
  );
  if (loadErr || !booking) return (
    <div className="min-h-screen bg-background flex flex-col items-center justify-center gap-4 px-8 text-center">
      <span className="material-symbols-outlined text-5xl text-error/60" style={{ fontVariationSettings: "'FILL' 1" }}>error</span>
      <p className="text-on-surface-variant text-sm">{loadErr ?? 'Cita no encontrada'}</p>
      <button onClick={() => navigate(-1)}
        className="flex items-center gap-2 px-4 py-2 bg-surface-container rounded-full text-sm text-on-surface border border-outline-variant">
        <span className="material-symbols-outlined text-base">arrow_back</span>Regresar
      </button>
    </div>
  );

  const editable = !['completed', 'cancelled'].includes(booking.status);

  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-28" {...(editing ? {} : sib.swipeHandlers)}>

      <ScreenHeader
        title={`Cita #${booking.id}`}
        screenTag="MobCitaDet"
        subtitle={booking.pet.name}
        backIcon={editing ? 'close' : 'arrow_back'}
        onBack={() => editing ? setEditing(false) : navigate(-1)}
        onTitlePrev={editing ? undefined : sib.onPrev}
        onTitleNext={editing ? undefined : sib.onNext}
        crumbs={crumbs}
        showBreadcrumbs={showBreadcrumbs}
        noCrumbs={editing}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); }}
        rightAction={editing ? (
          <button onClick={() => saveEdit()} disabled={saving}
            className="flex items-center gap-1.5 bg-primary text-on-primary px-4 py-2 rounded-full text-sm font-semibold active:scale-95 transition-all disabled:opacity-40">
            {saving
              ? <><span className="material-symbols-outlined text-base animate-spin">progress_activity</span>Guardando…</>
              : <><span className="material-symbols-outlined text-base">save</span>Guardar</>}
          </button>
        ) : editable ? (
          <button onClick={() => { setEditing(true); setSaveErr(null); }}
            className="p-2 rounded-full hover:bg-surface-container-high transition-colors">
            <span className="material-symbols-outlined text-on-surface">edit</span>
          </button>
        ) : null}
      />

      {/* ── Franja de estado ─────────────────────────────── */}
      <div className={`h-1.5 w-full ${STATUS_BAR[booking.status] ?? 'bg-outline-variant'}`} />

      <div className="flex flex-col gap-5 px-4 pt-4">

        {/* ── Info rápida: estado · fecha · hora (todo junto, sin tarjeta aparte) ── */}
        <div className="flex flex-col gap-2">
          <div className="flex items-center gap-2 flex-wrap">
            <span className={`text-xs font-bold px-3 py-1.5 rounded-full border ${STATUS_COLOR[booking.status] ?? ''}`}>
              {STATUS_LABEL[booking.status] ?? booking.status}
            </span>
            {booking.status === 'work_order' && (
              <button onClick={() => { setNoteErr(null); setShowNoteModal(true); }}
                className="flex items-center gap-1 text-xs font-bold px-3 py-1.5 rounded-full border bg-tertiary/10 text-tertiary border-tertiary/30 active:scale-95 transition-transform">
                <span className="material-symbols-outlined text-sm">edit_note</span>
                Nota{processNotes.length > 0 ? ` (${processNotes.length})` : ''}
              </button>
            )}
            {booking.order_folio && (
              <span className="font-mono text-xs font-bold text-on-surface-variant bg-surface-container px-2.5 py-1 rounded-lg border border-outline-variant">
                {booking.order_folio}
              </span>
            )}
            {booking.total > 0 && (
              <span className="ml-auto text-sm font-bold text-on-surface">${booking.total.toFixed(0)}</span>
            )}
          </div>
          <div className="flex items-center gap-x-3 gap-y-1 flex-wrap text-on-surface">
            <span className="flex items-center gap-1.5 text-sm font-semibold capitalize">
              <span className="material-symbols-outlined text-base text-on-surface-variant">event</span>
              {fmtLong(parseDateLocal(booking.scheduled_at))}
            </span>
            <span className="flex items-center gap-1.5 font-mono text-sm font-semibold">
              <span className="material-symbols-outlined text-base text-on-surface-variant">schedule</span>
              {booking.time}{booking.end_time ? ` → ${booking.end_time}` : ''}
              {booking.duration_minutes
                ? <span className="text-xs font-normal font-sans text-on-surface-variant ml-1">({minutesToHHMM(booking.duration_minutes)})</span>
                : null}
            </span>
          </div>
        </div>

        {/* ── Mascota y cliente ─────────────────────────── */}
        {/* Solo son enlaces si el usuario puede ver mascotas / clientes — un operador
            restringido (solo "ver agenda") vería la ficha rota / sin acceso al tocarlos. */}
        {(() => {
          const canPet = !!user?.can_view_pets;
          const canClient = !!user?.can_view_clients;
          const petInner = (
            <>
              <div className="w-12 h-12 rounded-xl bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                {booking.pet.photo
                  ? <img src={booking.pet.photo} className="w-full h-full object-cover" alt={booking.pet.name} />
                  : <span className="material-symbols-outlined text-primary text-2xl" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>}
              </div>
              <div className="flex-1 min-w-0 text-left">
                <p className="font-semibold text-on-surface">{booking.pet.name}</p>
                {booking.pet.breed && <p className="text-xs text-on-surface-variant truncate">{booking.pet.breed}</p>}
              </div>
              {canPet && <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>}
            </>
          );
          return (
            <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
              {canPet ? (
                <button onClick={() => { const nc = [{ label: `Cita #${booking.id}`, to: `/citas/${booking.id}` }]; setNavCrumbs(nc); navigate(`/mascotas/${booking.pet.id}`, { state: { _crumbs: nc } }); }}
                  className="w-full flex items-center gap-3 px-4 py-3 active:bg-surface-container transition-colors">
                  {petInner}
                </button>
              ) : (
                <div className="w-full flex items-center gap-3 px-4 py-3">{petInner}</div>
              )}

              {booking.client && (
                canClient ? (
                  <button onClick={() => { const nc = [{ label: `Cita #${booking.id}`, to: `/citas/${booking.id}` }]; setNavCrumbs(nc); navigate(`/clientes/${booking.client!.id}`, { state: { _crumbs: nc } }); }}
                    className="w-full flex items-center gap-3 px-4 py-3 border-t border-outline-variant active:bg-surface-container transition-colors">
                    <span className="material-symbols-outlined text-on-surface-variant text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
                    <div className="flex-1 text-left min-w-0">
                      <p className="text-sm font-medium text-on-surface truncate">{booking.client.name}</p>
                    </div>
                    <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
                  </button>
                ) : (
                  <div className="w-full flex items-center gap-3 px-4 py-3 border-t border-outline-variant">
                    <span className="material-symbols-outlined text-on-surface-variant text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
                    <div className="flex-1 text-left min-w-0">
                      <p className="text-sm font-medium text-on-surface truncate">{booking.client.name}</p>
                    </div>
                  </div>
                )
              )}
            </div>
          );
        })()}

        {/* ── Acciones de TODA la cita (las de cada servicio viven en "Servicios", abajo) ── */}
        {!editing && editable && acciones.length > 0 && (
          <section>
            <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
              Acciones <span className="font-normal normal-case text-on-surface-variant/60">· toda la cita</span>
            </p>
            <div className="flex flex-wrap gap-2">
              {acciones.map(action => (
                <button
                  key={action.value}
                  disabled={saving}
                  onClick={() => {
                    // Envuelto en try/catch a propósito: un error acá no debe fallar en
                    // silencio (React lo traga en consola, invisible en un teléfono) — se
                    // muestra en el banner de error que ya existe más abajo en la pantalla.
                    try {
                      if (action.realizada)            { markRealizada(); return; }
                      if (action.cobro)                { setNavCrumbs([{ label: `Cita #${booking.id}`, to: `/citas/${booking.id}` }]); navigate(`/citas/${booking.id}/cobro`); return; }
                      if (action.value === 'cancelled') { setShowCancel(true); return; }
                      if (action.unfulfilled) {
                        setUnfulfilledType(booking.status === 'scheduled' ? 'no_show' : 'unfulfillable');
                        setUnfulfilledNote('');
                        setShowUnfulfilled(true);
                        return;
                      }
                      if (action.value === 'work_order') {
                        const now = new Date();
                        const scheduled = parseDateLocal(booking.scheduled_at);
                        const diffMin = Math.round((now.getTime() - scheduled.getTime()) / 60000);
                        const outsideGrace = Math.abs(diffMin) > graceMinutes;
                        // Con 2+ servicios, siempre se elige cuál(es) arrancan ahora — la
                        // cita todavía está "scheduled" (este botón solo existe en ese
                        // estado), así que ninguna línea pudo haber arrancado todavía.
                        if (booking.services.length >= 2) {
                          setStartChecklist({
                            diffMinutes: outsideGrace ? diffMin : null,
                            selected: new Set(booking.services.map(s => s.booking_service_id)),
                          });
                          return;
                        }
                        if (outsideGrace) {
                          setGraceDialog({ diffMinutes: diffMin });
                          return;
                        }
                      }
                      changeStatus(action.value);
                    } catch (err) {
                      setSaveErr(`Error interno al procesar "${action.label}": ${err instanceof Error ? err.message : String(err)}`);
                      // El banner de error vive al fondo de la pantalla — sin esto, un error
                      // acá quedaría invisible fuera de la vista, pareciendo que "no pasó nada".
                      setTimeout(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }), 50);
                    }
                  }}
                  className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border font-semibold text-xs transition-colors active:scale-95 disabled:opacity-50 ${
                    action.danger
                      ? 'bg-error/8 border-error/30 text-error'
                      : 'bg-primary/8 border-primary/30 text-primary'
                  }`}
                >
                  <span className={`material-symbols-outlined text-base ${saving ? 'animate-spin' : ''}`} style={{ fontVariationSettings: "'FILL' 1" }}>
                    {saving ? 'progress_activity' : action.icon}
                  </span>
                  {action.label}
                </button>
              ))}
            </div>
          </section>
        )}

        {/* ── Saldo pendiente de una cita ya cerrada ("Pendiente de cobro") ── */}
        {!editing && booking.status === 'completed' && booking.total - totalPaid > 0.01 && (
          <div className="flex items-center gap-3 bg-secondary-container/40 border border-secondary-fixed rounded-2xl px-4 py-3.5">
            <span className="material-symbols-outlined text-on-secondary-container text-xl shrink-0">payments</span>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-bold text-on-secondary-container">
                Saldo pendiente: ${(booking.total - totalPaid).toFixed(2)}
              </p>
              <p className="text-xs text-on-secondary-container/80">Cerrada sin cobrar del todo — el cliente pagará después.</p>
            </div>
            <button
              onClick={() => { setNavCrumbs([{ label: `Cita #${booking.id}`, to: `/citas/${booking.id}` }]); navigate(`/citas/${booking.id}/cobro`); }}
              className="shrink-0 flex items-center gap-1.5 bg-secondary text-on-secondary px-3 py-2 rounded-xl text-xs font-bold active:scale-95 transition-transform">
              <span className="material-symbols-outlined text-base">point_of_sale</span>
              Cobrar
            </button>
          </div>
        )}

        {/* ── Modal cancelación ────────────────────────── */}
        {showCancel && (
          <div className="bg-error/8 border border-error/30 rounded-2xl px-4 py-4 flex flex-col gap-3">
            <p className="text-sm font-semibold text-error">Cancelar cita</p>
            <textarea
              value={cancelReason}
              onChange={e => setCancelReason(e.target.value)}
              rows={2}
              placeholder="Motivo de cancelación (opcional)"
              className="w-full bg-background border border-error/30 rounded-xl px-3 py-2 text-sm outline-none resize-none focus:border-error"
            />
            <div className="flex gap-2">
              <button onClick={() => { setShowCancel(false); setCancelReason(''); }}
                className="flex-1 py-2.5 rounded-xl text-sm border border-outline-variant text-on-surface-variant">
                Atrás
              </button>
              <button onClick={() => changeStatus('cancelled')} disabled={saving}
                className="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-error text-on-error disabled:opacity-50">
                Confirmar cancelación
              </button>
            </div>
          </div>
        )}

        {/* ── Modal "No se realizó" — no_show (falta del cliente) u otro motivo (no punitivo) ── */}
        {showUnfulfilled && (
          <div className="bg-error/8 border border-error/30 rounded-2xl px-4 py-4 flex flex-col gap-3">
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-error text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>person_off</span>
              <p className="text-sm font-semibold text-error">No se realizó</p>
            </div>

            <div className="flex flex-col gap-2">
              <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">¿Qué pasó?</p>
              <label className="flex items-center gap-2 text-sm text-on-surface">
                <input type="radio" name="unfulfilledType" checked={unfulfilledType === 'no_show'}
                  onChange={() => setUnfulfilledType('no_show')} className="w-4 h-4 accent-error" />
                El cliente no asistió — queda marcado como falta del cliente
              </label>
              <label className="flex items-center gap-2 text-sm text-on-surface">
                <input type="radio" name="unfulfilledType" checked={unfulfilledType === 'unfulfillable'}
                  onChange={() => setUnfulfilledType('unfulfillable')} className="w-4 h-4 accent-error" />
                Otro motivo (ej. la mascota no cooperó, el operador se lastimó) — no se marca como falta del cliente
              </label>
            </div>

            <textarea
              value={unfulfilledNote}
              onChange={e => setUnfulfilledNote(e.target.value)}
              rows={2}
              placeholder="Nota (opcional): el animal no cooperó, el groomer se lastimó…"
              className="w-full bg-background border border-error/30 rounded-xl px-3 py-2 text-sm outline-none resize-none focus:border-error"
            />
            <div className="flex gap-2">
              <button onClick={() => { setShowUnfulfilled(false); setUnfulfilledNote(''); }}
                className="flex-1 py-2.5 rounded-xl text-sm border border-outline-variant text-on-surface-variant">
                Cancelar
              </button>
              <button onClick={() => changeStatus(unfulfilledType, unfulfilledNote)} disabled={saving}
                className="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-error text-on-error disabled:opacity-50">
                Confirmar
              </button>
            </div>
          </div>
        )}

        {/* ── Alerta de tolerancia ──────────────────────── */}
        {graceDialog && (
          <div className="bg-warning/8 border border-warning/40 rounded-2xl px-4 py-4 flex flex-col gap-3"
               style={{ background: 'color-mix(in srgb, var(--color-error) 6%, transparent)', borderColor: 'color-mix(in srgb, var(--color-error) 30%, transparent)' }}>
            <div className="flex items-start gap-3">
              <span className="material-symbols-outlined text-2xl shrink-0 mt-0.5"
                    style={{ color: 'var(--color-error)', fontVariationSettings: "'FILL' 1" }}>
                schedule_send
              </span>
              <div>
                <p className="text-sm font-bold" style={{ color: 'var(--color-error)' }}>
                  {graceDialog.diffMinutes > 0
                    ? `Inicio con ${graceDialog.diffMinutes} min de retraso`
                    : `Inicio ${Math.abs(graceDialog.diffMinutes)} min antes de la hora`}
                </p>
                <p className="text-xs mt-1" style={{ color: 'var(--color-on-surface-variant)' }}>
                  {graceDialog.diffMinutes > 0
                    ? `La cita era a las ${booking.time}. ¿Confirmas que está comenzando ahora?`
                    : `La cita es a las ${booking.time}. ¿Confirmas iniciarla antes de la hora programada?`}
                </p>
              </div>
            </div>
            <div className="flex gap-2">
              <button onClick={() => setGraceDialog(null)}
                className="flex-1 py-2.5 rounded-xl text-sm border border-outline-variant text-on-surface-variant">
                Cancelar
              </button>
              <button onClick={() => { setGraceDialog(null); changeStatus('work_order'); }} disabled={saving}
                className="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-primary text-on-primary disabled:opacity-50">
                Sí, iniciar
              </button>
            </div>
          </div>
        )}

        {/* ── Checklist: qué servicio(s) iniciar ahora (2+ servicios) ─── */}
        {startChecklist && (
          <div className="bg-surface border border-outline-variant rounded-2xl px-4 py-4 flex flex-col gap-3">
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-primary text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>checklist</span>
              <p className="text-sm font-bold text-on-surface">¿Qué servicio(s) inician ahora?</p>
            </div>

            {startChecklist.diffMinutes !== null && (
              <p className="text-xs font-semibold" style={{ color: 'var(--color-error)' }}>
                {startChecklist.diffMinutes > 0
                  ? `La cita era a las ${booking.time} — inicio con ${startChecklist.diffMinutes} min de retraso.`
                  : `La cita es a las ${booking.time} — iniciando ${Math.abs(startChecklist.diffMinutes)} min antes de la hora.`}
              </p>
            )}

            <div className="flex flex-col gap-2">
              {booking.services.map(s => {
                const checked = startChecklist.selected.has(s.booking_service_id);
                return (
                  <button
                    key={s.booking_service_id}
                    type="button"
                    onClick={() => setStartChecklist(prev => {
                      if (!prev) return prev;
                      const next = new Set(prev.selected);
                      if (next.has(s.booking_service_id)) next.delete(s.booking_service_id); else next.add(s.booking_service_id);
                      return { ...prev, selected: next };
                    })}
                    className={`flex items-center gap-3 px-3 py-2.5 rounded-xl border text-left transition-colors ${
                      checked ? 'bg-primary/10 border-primary/40' : 'bg-surface-container border-outline-variant'
                    }`}
                  >
                    <span
                      className={`material-symbols-outlined text-xl shrink-0 ${checked ? 'text-primary' : 'text-on-surface-variant'}`}
                      style={{ fontVariationSettings: `'FILL' ${checked ? 1 : 0}` }}
                    >
                      {checked ? 'check_box' : 'check_box_outline_blank'}
                    </span>
                    <div className="min-w-0">
                      <p className="text-sm font-semibold text-on-surface truncate">{s.name}</p>
                      <p className="text-xs text-on-surface-variant truncate">{s.operator_name ?? 'Sin profesional asignado'}</p>
                    </div>
                  </button>
                );
              })}
            </div>

            <p className="text-[11px] text-on-surface-variant">
              Lo que dejes sin marcar queda pendiente — se puede iniciar después, por separado, desde "Servicio / Operador".
            </p>

            <div className="flex gap-2">
              <button onClick={() => setStartChecklist(null)} disabled={startingChecklist}
                className="flex-1 py-2.5 rounded-xl text-sm border border-outline-variant text-on-surface-variant disabled:opacity-50">
                Cancelar
              </button>
              <button onClick={confirmStartChecklist} disabled={startingChecklist || startChecklist.selected.size === 0}
                className="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-primary text-on-primary disabled:opacity-50">
                {startingChecklist ? 'Iniciando…' : `Iniciar (${startChecklist.selected.size})`}
              </button>
            </div>
          </div>
        )}

        {/* ── Modal nota de proceso ─────────────────────── */}
        {showNoteModal && (
          <div className="bg-tertiary/8 border border-tertiary/30 rounded-2xl px-4 py-4 flex flex-col gap-3">
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-tertiary text-xl">edit_note</span>
              <p className="text-sm font-semibold text-tertiary">Nota del proceso en curso</p>
            </div>
            <textarea
              value={noteText}
              onChange={e => setNoteText(e.target.value)}
              rows={3}
              autoFocus
              placeholder="Ej. Se detectó nudo en la pata trasera, se avisó al dueño…"
              className="w-full bg-background border border-tertiary/30 rounded-xl px-3 py-2 text-sm outline-none resize-none focus:border-tertiary"
            />
            {noteErr && <p className="text-xs text-error">{noteErr}</p>}
            <div className="flex gap-2">
              <button onClick={() => { setShowNoteModal(false); setNoteText(''); setNoteErr(null); }}
                className="flex-1 py-2.5 rounded-xl text-sm border border-outline-variant text-on-surface-variant">
                Cancelar
              </button>
              <button onClick={saveNote} disabled={savingNote || !noteText.trim()}
                className="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-tertiary text-on-tertiary disabled:opacity-50">
                {savingNote ? 'Guardando…' : 'Guardar nota'}
              </button>
            </div>
          </div>
        )}


        {/* ══════════════════════════════════════════════
            MODO EDICIÓN
            ══════════════════════════════════════════════ */}
        {editing && (
          <>
            {booking.status !== 'scheduled' && (
              <div className="flex items-start gap-2 bg-surface-container rounded-2xl px-4 py-3">
                <span className="material-symbols-outlined text-on-surface-variant text-lg shrink-0">info</span>
                <p className="text-xs text-on-surface-variant">
                  Esta cita ya está en proceso — la fecha y hora ya no se pueden mover. Puedes seguir editando servicios, operador y notas.
                </p>
              </div>
            )}

            {/* Fecha */}
            {booking.status === 'scheduled' && (
            <section>
              <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">Fecha</p>
              <div className="flex items-center gap-2 overflow-x-auto hide-scrollbar pb-1">
                {[0, 1, 2, 3, 4, 5, 6].map(offset => {
                  const d = addDays(today, offset);
                  const sel = localDateStr(d) === localDateStr(selDate);
                  return (
                    <button key={offset} onClick={() => setSelDate(d)}
                      className={`flex flex-col items-center px-3 py-2 rounded-xl shrink-0 transition-colors ${
                        sel ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface border border-outline-variant'
                      }`}>
                      <span className="text-[10px] uppercase tracking-wide opacity-70">{fmtChip(d, offset)}</span>
                      <span className="text-sm font-bold">{d.getDate()}</span>
                    </button>
                  );
                })}
                <input ref={detDateInputRef} type="date" id="det-date" className="sr-only" value={localDateStr(selDate)}
                  onChange={e => {
                    if (e.target.value) {
                      const [y, m, d] = e.target.value.split('-').map(Number);
                      setSelDate(new Date(y, m - 1, d));
                    }
                  }} />
                <button
                  type="button"
                  onClick={() => {
                    // En Chrome de escritorio, enfocar el input (ej. vía label) no abre el
                    // calendario nativo — solo showPicker() lo hace explícitamente, mismo
                    // patrón ya usado en MobCitaNueva.tsx para este problema.
                    const el = detDateInputRef.current;
                    if (el && typeof el.showPicker === 'function') el.showPicker();
                    else el?.focus();
                  }}
                  className="p-2 rounded-xl bg-surface-container border border-outline-variant shrink-0"
                  aria-label="Elegir otra fecha del calendario"
                >
                  <span className="material-symbols-outlined text-on-surface-variant text-xl">calendar_month</span>
                </button>
              </div>
            </section>
            )}

            {/* Servicios */}
            <section>
              <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">Servicios</p>
              <div className="flex flex-col gap-2">
                {catalog.map(svc => {
                  const sel = selSvcs.includes(svc.id);
                  return (
                    <button key={svc.id} onClick={() => setSelSvcs(prev =>
                      prev.includes(svc.id) ? prev.filter(x => x !== svc.id) : [...prev, svc.id]
                    )}
                      className={`flex items-center gap-3 px-4 py-3 rounded-2xl border transition-colors text-left ${
                        sel ? 'bg-primary/10 border-primary/40' : 'bg-surface-container border-outline-variant'
                      }`}>
                      <span className={`material-symbols-outlined text-xl shrink-0 ${sel ? 'text-primary' : 'text-on-surface-variant'}`}
                        style={{ fontVariationSettings: `'FILL' ${sel ? 1 : 0}` }}>
                        {sel ? 'check_circle' : 'radio_button_unchecked'}
                      </span>
                      <div className="flex-1 min-w-0">
                        <p className={`text-sm font-semibold truncate ${sel ? 'text-primary' : 'text-on-surface'}`}>{svc.name}</p>
                        <div className="flex items-center gap-2 mt-0.5">
                          {svc.price > 0 && <span className="text-xs text-on-surface-variant">${svc.price.toFixed(0)}</span>}
                          {svc.duration_minutes && (
                            <span className="flex items-center gap-1 text-xs text-on-surface-variant">
                              <span className="material-symbols-outlined text-xs">schedule</span>
                              {minutesToHHMM(svc.duration_minutes)}
                            </span>
                          )}
                        </div>
                      </div>
                    </button>
                  );
                })}
              </div>
            </section>

            {/* Duración */}
            <section>
              <div className="flex items-center justify-between mb-2">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Duración</p>
                {customDur !== null && customDur !== catalogDuration && (
                  <button onClick={() => setCustomDur(null)} className="text-xs text-primary flex items-center gap-1">
                    <span className="material-symbols-outlined text-sm">restart_alt</span>Restablecer
                  </button>
                )}
              </div>
              <div className="flex items-center bg-surface-container border border-outline-variant rounded-2xl overflow-hidden">
                <button onClick={() => adjustDur(-DUR_STEP)} disabled={effectiveDuration <= 15}
                  className="flex items-center justify-center w-14 h-14 active:bg-surface-container-high transition-colors disabled:opacity-30">
                  <span className="material-symbols-outlined text-2xl">remove</span>
                </button>
                <div className="flex-1 flex flex-col items-center py-3">
                  <span className="text-2xl font-bold tabular-nums">{minutesToHHMM(effectiveDuration)}</span>
                  <span className="text-[10px] text-on-surface-variant mt-0.5">
                    {customDur !== null && customDur !== catalogDuration ? 'Ajustado' : catalogDuration > 0 ? 'Del catálogo' : 'Estimado'}
                  </span>
                </div>
                <button onClick={() => adjustDur(DUR_STEP)} disabled={effectiveDuration >= 480}
                  className="flex items-center justify-center w-14 h-14 active:bg-surface-container-high transition-colors disabled:opacity-30">
                  <span className="material-symbols-outlined text-2xl">add</span>
                </button>
              </div>
            </section>

            {/* Operador */}
            <section>
              <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
                Operador <span className="font-normal normal-case text-on-surface-variant/50">(opcional)</span>
              </p>
              <div className="flex flex-wrap gap-2">
                <button onClick={() => setSelOp(null)}
                  className={`flex items-center gap-1.5 px-3 py-2 rounded-full text-sm border transition-colors ${
                    selOp === null
                      ? 'bg-surface-container-high border-outline text-on-surface font-semibold'
                      : 'bg-surface-container border-outline-variant text-on-surface-variant'
                  }`}>
                  <span className="material-symbols-outlined text-base">person_off</span>Sin asignar
                </button>
                {operators.map(op => {
                  const sel = selOp === op.id;
                  return (
                    <button key={op.id} onClick={() => setSelOp(sel ? null : op.id)}
                      className={`flex items-center gap-2 px-3 py-2 rounded-full text-sm border transition-colors ${
                        sel ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container border-outline-variant text-on-surface'
                      }`}>
                      {op.photo_url
                        ? <img src={op.photo_url} className="w-5 h-5 rounded-full object-cover" alt="" />
                        : <span className="material-symbols-outlined text-base" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>}
                      <span className="font-medium">{op.name.split(' ')[0]}</span>
                    </button>
                  );
                })}
              </div>
            </section>

            {/* Horario */}
            {booking.status === 'scheduled' && (
            <section>
              <div className="flex items-center justify-between mb-2">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">
                  Horario — {fmtLong(selDate)}
                </p>
                {selSlot && endTime && (
                  <span className="text-xs text-primary font-semibold">{selSlot} → {endTime}</span>
                )}
              </div>

              {loadSlots ? (
                <div className="flex items-center gap-2 py-6 text-on-surface-variant text-sm">
                  <span className="material-symbols-outlined animate-spin text-xl">progress_activity</span>
                  Verificando disponibilidad…
                </div>
              ) : (
                <div className="grid grid-cols-4 gap-2">
                  {ALL_SLOTS.map(slot => {
                    const occ   = occupied.has(slot);
                    const isSel = selSlot === slot;
                    const inRng = isInRange(slot);
                    return (
                      <button key={slot} disabled={occ} onClick={() => setSelSlot(isSel ? '' : slot)}
                        className={`py-2.5 rounded-xl text-sm font-mono font-semibold transition-all ${
                          occ   ? 'bg-error-container text-on-error-container border border-error/20 cursor-not-allowed line-through' :
                          isSel ? 'bg-primary text-on-primary shadow-md scale-105' :
                          inRng ? 'bg-primary/25 text-primary border border-primary/30' :
                                  'bg-surface-container text-on-surface border border-outline-variant active:scale-95'
                        }`}>
                        {slot}
                      </button>
                    );
                  })}
                </div>
              )}
            </section>
            )}

            {/* Notas */}
            <section>
              <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">Notas</p>
              <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={3}
                placeholder="Instrucciones especiales…"
                className="w-full bg-surface-container border border-outline-variant rounded-2xl px-4 py-3 text-sm text-on-surface outline-none focus:border-primary resize-none" />
            </section>
          </>
        )}

        {/* ── Vista (no edición) ───────────────────────── */}
        {!editing && (
          <>
            {/* (La fecha y hora ya están arriba, en el bloque de estado — no se repite acá.) */}

            {/* ── Profesional por servicio (agendada o "En proceso" — antes solo aparecía en
                "En proceso", pero desde que SYNC-040 asigna operador por servicio ya desde que
                se agenda la cita, tiene sentido mostrarlo también con la cita solo agendada) ── */}
            {(booking.status === 'scheduled' || booking.status === 'work_order') && booking.services.length > 0 && (
              <section>
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">Servicios</p>
                {(() => {
                  const excluded = booking.services.filter(l => l.cancelled_at || l.not_performed_at).length;
                  return excluded > 0 ? (
                    <p className="text-xs text-error mb-2 flex items-center gap-1">
                      <span className="material-symbols-outlined text-sm" style={{ fontVariationSettings: "'FILL' 1" }}>info</span>
                      {excluded} servicio{excluded > 1 ? 's' : ''} no se cobra{excluded > 1 ? 'n' : ''} (no realizado / cancelado).
                    </p>
                  ) : null;
                })()}
                <div className="flex flex-col gap-2">
                  {booking.services.map(line => {
                    const st = lineStateOf(line);
                    const voided = st === 'cancelled' || st === 'not_performed';
                    const busy = lineActionId === line.booking_service_id;
                    const assigning = assigningLine?.booking_service_id === line.booking_service_id;
                    const chip = 'text-xs font-semibold px-3 py-1.5 rounded-xl disabled:opacity-50 active:scale-95 transition-transform';
                    const stateChip =
                      st === 'completed'     ? { label: 'Completado',    cls: 'bg-tertiary/10 text-tertiary' } :
                      st === 'in_progress'   ? { label: 'En proceso',    cls: 'bg-secondary/10 text-secondary' } :
                      st === 'not_performed' ? { label: 'No se realizó', cls: 'bg-error/10 text-error' } :
                      st === 'cancelled'     ? { label: 'Cancelada',     cls: 'bg-error/10 text-error' } :
                                               { label: 'Pendiente',     cls: 'bg-surface-container text-on-surface-variant' };
                    return (
                    <div key={line.booking_service_id} className={`bg-surface border rounded-2xl px-4 py-3 flex flex-col gap-2.5 ${voided ? 'border-error/30 opacity-70' : 'border-outline-variant'}`}>
                      {/* Encabezado: nombre + meta (hora · duración · precio · operador · externo) + chip de estado */}
                      <div className="flex items-start gap-2">
                        <div className="flex-1 min-w-0">
                          <p className={`text-sm font-semibold ${voided ? 'line-through text-on-surface-variant' : 'text-on-surface'}`}>{line.name}</p>
                          <div className="flex items-center gap-1.5 mt-1 flex-wrap">
                            {booking.services.length > 1 && line.start_time && (
                              <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant border border-outline-variant">
                                <span className="font-mono">{line.start_time}</span>
                                {line.duration_minutes ? ` · ${minutesToHHMM(line.duration_minutes)}` : ''}
                              </span>
                            )}
                            <span className="text-xs font-semibold text-on-surface-variant">${line.price.toFixed(2)}</span>
                            {line.operator_name ? (
                              <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-primary/10 text-primary">{line.operator_name}</span>
                            ) : (
                              <span className="text-xs text-on-surface-variant">Sin asignar</span>
                            )}
                            {line.is_external && (
                              <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-error/10 text-error border border-error/30">
                                Externo{line.external_cost !== null ? ` — $${line.external_cost.toFixed(2)}` : ''}
                              </span>
                            )}
                          </div>
                        </div>
                        <span className={`text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 ${stateChip.cls}`}>{stateChip.label}</span>
                      </div>

                      {voided && (line.cancellation_reason || line.not_performed_reason) && (
                        <p className="text-xs text-on-surface-variant">Motivo: {line.cancellation_reason || line.not_performed_reason}</p>
                      )}

                      {/* Acciones de este servicio */}
                      <div className="flex items-center gap-1.5 flex-wrap">
                        {voided ? (
                          <button type="button" onClick={() => reactivateLine(line.booking_service_id)} disabled={busy}
                            className={`${chip} border border-primary text-primary`}>
                            {busy ? 'Reactivando…' : 'Reactivar'}
                          </button>
                        ) : st === 'completed' ? (
                          <span className="text-xs text-on-surface-variant">Servicio completado.</span>
                        ) : (
                          <>
                            {st === 'in_progress' ? (
                              <button type="button" onClick={() => completeSingleLine(line.booking_service_id)}
                                disabled={completingLineId === line.booking_service_id}
                                className={`${chip} bg-tertiary/10 text-tertiary`}>
                                {completingLineId === line.booking_service_id ? 'Terminando…' : 'Terminar'}
                              </button>
                            ) : (
                              <>
                                <button type="button" onClick={() => startSingleLine(line.booking_service_id)}
                                  disabled={startingLineId === line.booking_service_id}
                                  className={`${chip} bg-primary/10 text-primary`}>
                                  {startingLineId === line.booking_service_id ? 'Iniciando…' : 'Iniciar'}
                                </button>
                                <button type="button" onClick={() => realizadaSingleLine(line.booking_service_id)} disabled={busy}
                                  className={`${chip} bg-tertiary/10 text-tertiary`}>
                                  Realizada
                                </button>
                              </>
                            )}
                            <button type="button" onClick={() => { setVoidPanel({ lineId: line.booking_service_id, kind: 'not_performed' }); setVoidReason(''); }}
                              className={`${chip} border border-error text-error`}>
                              No se realizó
                            </button>
                            <button type="button" onClick={() => { setVoidPanel({ lineId: line.booking_service_id, kind: 'cancelled' }); setVoidReason(''); }}
                              className={`${chip} border border-error text-error`}>
                              Cancelar
                            </button>
                          </>
                        )}
                        {!voided && st !== 'completed' && (
                          <button type="button" onClick={() => (assigning ? closeAssign() : openAssign(line))}
                            className={`${chip} ${line.operator_name ? 'border border-secondary text-secondary' : 'bg-amber-500 border border-amber-500 text-white'}`}>
                            {assigning ? 'Cerrar' : line.operator_name ? 'Reasignar operador / precio' : 'Asignar operador'}
                          </button>
                        )}
                      </div>

                      {/* Sub-panel de motivo (No se realizó / Cancelar) de ESTE servicio */}
                      {voidPanel?.lineId === line.booking_service_id && (
                        <div className="bg-error/8 border border-error/30 rounded-2xl px-3 py-3 flex flex-col gap-2">
                          <p className="text-xs font-semibold text-error">
                            {voidPanel.kind === 'not_performed' ? 'Marcar como no realizado' : 'Cancelar este servicio'}
                          </p>
                          <textarea value={voidReason} onChange={e => setVoidReason(e.target.value)} rows={2}
                            placeholder="Motivo (opcional)"
                            className="w-full bg-background border border-error/30 rounded-xl px-3 py-2 text-sm outline-none resize-none focus:border-error" />
                          <div className="flex gap-2">
                            <button onClick={() => { setVoidPanel(null); setVoidReason(''); }}
                              className="flex-1 py-2 rounded-xl text-sm border border-outline-variant text-on-surface-variant">
                              Cerrar
                            </button>
                            <button onClick={confirmVoidLine} disabled={lineActionId === voidPanel.lineId}
                              className="flex-1 py-2 rounded-xl text-sm font-semibold bg-error text-on-error disabled:opacity-50">
                              Confirmar
                            </button>
                          </div>
                        </div>
                      )}

                      {/* Formulario de reasignar operador · precio · costo externo */}
                      {assigning && (
                        <div className="bg-secondary/8 border border-secondary/30 rounded-2xl px-4 py-4 flex flex-col gap-3">
                          <div>
                            <label className="text-xs text-on-surface-variant mb-1 block">Especialista / Operador</label>
                            <select
                              value={assignOperator}
                              onChange={e => setAssignOperator(e.target.value ? Number(e.target.value) : '')}
                              className="w-full bg-background border border-outline-variant rounded-xl px-3 py-2 text-sm outline-none focus:border-secondary"
                            >
                              <option value="">Selecciona un profesional…</option>
                              {operators.map(op => (
                                <option key={op.id} value={op.id}>{op.name}{op.role ? ` (${op.role})` : ''}</option>
                              ))}
                            </select>
                          </div>

                          <label className="flex items-center gap-2 text-sm text-on-surface">
                            <input type="checkbox" checked={assignExternal} onChange={e => setAssignExternal(e.target.checked)}
                              className="w-4 h-4 accent-secondary" />
                            Es servicio externo (ej. cremación, anestesia externa)
                          </label>

                          {assignExternal && (
                            <div>
                              <label className="text-xs text-on-surface-variant mb-1 block">Costo del proveedor externo</label>
                              <input type="number" inputMode="decimal" step="0.01" min="0" value={assignCost}
                                onChange={e => setAssignCost(e.target.value)}
                                className="w-full bg-background border border-outline-variant rounded-xl px-3 py-2 text-sm outline-none focus:border-secondary" />
                              <p className="text-[11px] text-on-surface-variant mt-1">Lo que cobra el proveedor externo — distinto del precio de venta al cliente.</p>
                            </div>
                          )}

                          <div>
                            <div className="flex items-center justify-between mb-1">
                              <label className="text-xs text-on-surface-variant">Precio de venta al cliente</label>
                              {assignSuggestedPrice && (
                                <button type="button" onClick={() => setAssignPrice(assignSuggestedPrice)}
                                  className="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-tertiary/15 text-tertiary">
                                  Sugerido: ${assignSuggestedPrice} (usar)
                                </button>
                              )}
                            </div>
                            <input type="number" inputMode="decimal" step="0.01" min="0" value={assignPrice}
                              onChange={e => setAssignPrice(e.target.value)}
                              className="w-full bg-background border border-outline-variant rounded-xl px-3 py-2 text-sm outline-none focus:border-secondary" />
                            {assignSuggestedPrice && (
                              <p className="text-[11px] text-on-surface-variant mt-1">
                                El costo externo cambió respecto al capturado antes — el precio sugerido mantiene la misma proporción, pero es editable, no se aplica solo.
                              </p>
                            )}
                          </div>

                          {assignErr && <p className="text-xs text-error">{assignErr}</p>}
                          <div className="flex gap-2">
                            <button onClick={closeAssign}
                              className="flex-1 py-2.5 rounded-xl text-sm border border-outline-variant text-on-surface-variant">
                              Cancelar
                            </button>
                            <button onClick={saveAssign} disabled={savingAssign || !assignOperator}
                              className="flex-1 py-2.5 rounded-xl text-sm font-semibold bg-secondary text-on-secondary disabled:opacity-50">
                              {savingAssign ? 'Guardando…' : 'Confirmar asignación'}
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                    );
                  })}
                </div>
              </section>
            )}

            {/* ── Movimientos: servicios + cobros en tabla ordenable ── */}
            {txRows.length > 0 && (
              <section>
                <div className="flex items-center justify-between mb-2">
                  <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Movimientos</p>
                  <span className={`text-xs font-bold font-mono px-2 py-0.5 rounded-lg border ${
                    booking.order_folio
                      ? 'text-primary bg-primary/8 border-primary/30'
                      : (STATUS_COLOR[booking.status] ?? 'text-on-surface-variant bg-surface-container border-outline-variant')
                  }`}>
                    {booking.order_folio ?? (STATUS_LABEL[booking.status] ?? booking.status)}
                  </span>
                </div>

                <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
                  {/* Encabezado sorteable */}
                  <div className="flex items-center gap-2 px-3 py-2.5 border-b border-outline-variant bg-surface-container/50">
                    <SortBtn label="Tipo"        col="tipo"        sortKey={txSortKey} direction={txDir} onToggle={txToggle} className="w-[88px] shrink-0" />
                    <SortBtn label="Descripción" col="descripcion" sortKey={txSortKey} direction={txDir} onToggle={txToggle} className="flex-1" />
                    <SortBtn label="Monto"       col="monto"       sortKey={txSortKey} direction={txDir} onToggle={txToggle} className="w-16 justify-end shrink-0" />
                  </div>

                  {/* Filas */}
                  {sortedTxRows.map((row, i) => (
                    <div
                      key={row.key}
                      className={`flex items-center gap-2 px-3 py-2.5 ${i > 0 ? 'border-t border-outline-variant' : ''}`}
                    >
                      {/* Tipo */}
                      <div className="w-[88px] flex items-center gap-1 shrink-0">
                        <span
                          className={`material-symbols-outlined text-base ${row.tipo === 'Servicio' ? 'text-primary' : 'text-tertiary'}`}
                          style={{ fontVariationSettings: "'FILL' 1" }}
                        >
                          {row.tipo === 'Servicio' ? 'content_cut' : 'payments'}
                        </span>
                        <span className={`text-xs font-semibold truncate ${row.tipo === 'Servicio' ? 'text-primary' : 'text-tertiary'}`}>
                          {row.tipo}
                        </span>
                      </div>

                      {/* Descripción */}
                      <div className="flex-1 min-w-0">
                        <p className="text-sm text-on-surface truncate">{row.descripcion}</p>
                        {row.detalle && (
                          <p className="text-xs text-on-surface-variant">{row.detalle}</p>
                        )}
                      </div>

                      {/* Monto */}
                      <span className={`text-sm font-bold w-16 text-right shrink-0 ${
                        row.tipo === 'Servicio' ? 'text-on-surface' : 'text-tertiary'
                      }`}>
                        ${row.monto.toFixed(2)}
                      </span>
                    </div>
                  ))}

                  {/* Pie: total cobrado */}
                  {totalPaid > 0 && (
                    <div className="border-t-2 border-outline-variant px-3 py-2.5 flex items-center justify-between bg-surface-container">
                      <span className="text-xs font-bold text-on-surface-variant uppercase tracking-wide">
                        Total cobrado
                      </span>
                      <span className="text-sm font-bold text-tertiary">${totalPaid.toFixed(2)}</span>
                    </div>
                  )}
                </div>
              </section>
            )}

            {/* Operador (omitido cuando ya se muestra por línea arriba, en "Servicio / Operador") */}
            {booking.operator && !((booking.status === 'scheduled' || booking.status === 'work_order') && booking.services.length > 0) && (
              <div className="bg-surface border border-outline-variant rounded-2xl px-4 py-3 flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-secondary/10 overflow-hidden flex items-center justify-center shrink-0">
                  {booking.operator.photo_url
                    ? <img src={booking.operator.photo_url} className="w-full h-full object-cover" alt="" />
                    : <span className="material-symbols-outlined text-secondary" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>}
                </div>
                <div>
                  <p className="text-xs text-on-surface-variant">Operador</p>
                  <p className="text-sm font-semibold text-on-surface">{booking.operator.name}</p>
                </div>
              </div>
            )}

            {/* Notas de la cita (editable en línea, sin entrar al modo edición completo — siempre,
                incluso con la cita ya completada/cancelada: corregir una nota no reabre nada operativo) */}
            <div className="bg-surface-container-low border border-outline-variant rounded-2xl px-4 py-3">
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1">Notas de la cita</p>
                {editingBookingNote ? (
                  <div className="flex flex-col gap-2">
                    <textarea
                      value={bookingNoteText}
                      onChange={e => setBookingNoteText(e.target.value)}
                      rows={3}
                      autoFocus
                      placeholder="Instrucciones especiales…"
                      className="w-full bg-background border border-outline-variant rounded-xl px-3 py-2 text-sm outline-none resize-none focus:border-primary"
                    />
                    <div className="flex gap-2">
                      <button onClick={cancelEditBookingNote}
                        className="flex-1 py-2 rounded-xl text-xs border border-outline-variant text-on-surface-variant">
                        Cancelar
                      </button>
                      <button onClick={saveBookingNote} disabled={savingBookingNote}
                        className="flex-1 py-2 rounded-xl text-xs font-semibold bg-primary text-on-primary disabled:opacity-50">
                        {savingBookingNote ? 'Guardando…' : 'Guardar'}
                      </button>
                    </div>
                  </div>
                ) : (
                  <button onClick={startEditBookingNote} className="w-full text-left">
                    {booking.notes ? (
                      <p className="text-sm text-on-surface leading-relaxed">{booking.notes}</p>
                    ) : (
                      <p className="text-sm text-on-surface-variant/60 italic">Sin notas — toca para agregar</p>
                    )}
                    <span className="text-[11px] text-primary mt-1 inline-flex items-center gap-1">
                      <span className="material-symbols-outlined text-xs">edit</span>Editar
                    </span>
                  </button>
                )}
            </div>

            {/* Notas de proceso */}
            {processNotes.length > 0 && (
              <section>
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
                  Notas del proceso <span className="font-normal normal-case text-on-surface-variant/50">(toca una para editarla)</span>
                </p>
                <div className="flex flex-col gap-2">
                  {processNotes.map(n => (
                    <div key={n.id} className="bg-tertiary-container/20 border border-tertiary-fixed-dim/40 rounded-2xl px-4 py-3">
                      {editingProcessNoteId === n.id ? (
                        <div className="flex flex-col gap-2">
                          <textarea
                            value={editingProcessText}
                            onChange={e => setEditingProcessText(e.target.value)}
                            rows={3}
                            autoFocus
                            className="w-full bg-background border border-tertiary/30 rounded-xl px-3 py-2 text-sm outline-none resize-none focus:border-tertiary"
                          />
                          <div className="flex gap-2">
                            <button onClick={cancelEditProcessNote}
                              className="flex-1 py-2 rounded-xl text-xs border border-outline-variant text-on-surface-variant">
                              Cancelar
                            </button>
                            <button onClick={saveEditProcessNote} disabled={savingProcessNoteId === n.id || !editingProcessText.trim()}
                              className="flex-1 py-2 rounded-xl text-xs font-semibold bg-tertiary text-on-tertiary disabled:opacity-50">
                              {savingProcessNoteId === n.id ? 'Guardando…' : 'Guardar'}
                            </button>
                          </div>
                        </div>
                      ) : (
                        <button onClick={() => startEditProcessNote(n)} className="w-full text-left">
                          <p className="text-sm text-on-surface leading-relaxed">{n.note}</p>
                          <p className="text-[11px] text-on-surface-variant mt-1.5 flex items-center gap-1">
                            {n.author ? `${n.author} · ` : ''}
                            {new Date(n.created_at.replace(' ', 'T')).toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                            <span className="material-symbols-outlined text-xs ml-auto">edit</span>
                          </p>
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              </section>
            )}

            {/* Motivo de cancelación / observación no-show */}
            {booking.cancellation_reason && (
              <div className="bg-error/8 border border-error/30 rounded-2xl px-4 py-3">
                <p className="text-xs font-semibold text-error uppercase tracking-wide mb-1">
                  {booking.status === 'no_show' ? 'Observación' : 'Motivo de cancelación'}
                </p>
                <p className="text-sm text-on-surface leading-relaxed">{booking.cancellation_reason}</p>
              </div>
            )}
          </>
        )}

        {/* ── Error ────────────────────────────────────── */}
        {saveErr && (
          <div className="bg-error/10 border border-error/30 rounded-2xl px-4 py-3 flex items-start gap-2">
            <span className="material-symbols-outlined text-error text-xl shrink-0 mt-0.5" style={{ fontVariationSettings: "'FILL' 1" }}>error</span>
            <div className="flex-1 min-w-0">
              <p className="text-sm text-error">{saveErr}</p>
              {offerOverride && (
                <button
                  onClick={() => saveEdit(true)}
                  className="mt-2 text-xs font-bold text-error underline underline-offset-2"
                >
                  Agendar de todas formas
                </button>
              )}
            </div>
          </div>
        )}

      </div>

      {/* ── FAB guardar (modo edición) ───────────────── */}
      {editing && (
        <div className="fixed bottom-16 left-4 right-4 z-30">
          <button onClick={() => saveEdit()} disabled={saving || !selSlot}
            className="w-full flex items-center justify-center gap-2 bg-primary text-on-primary py-4 rounded-2xl text-base font-bold shadow-lg active:scale-[0.98] transition-all disabled:opacity-40">
            {saving
              ? <><span className="material-symbols-outlined animate-spin">progress_activity</span>Guardando…</>
              : <><span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>save</span>
                  Guardar cambios · {selSlot}{endTime ? ` → ${endTime}` : ''}</>}
          </button>
        </div>
      )}

      {/* ── Toast de éxito ───────────────────────────── */}
      {toast && (
        <div className="fixed bottom-20 left-4 right-4 z-50 flex items-center gap-3 bg-inverse-surface text-inverse-on-surface rounded-2xl px-4 py-3 shadow-xl"
             style={{ background: 'var(--color-on-surface, #1c1b1f)', color: 'var(--color-surface, #fffbfe)' }}>
          <span className="material-symbols-outlined text-xl shrink-0" style={{ fontVariationSettings: "'FILL' 1", color: '#4ade80' }}>check_circle</span>
          <span className="flex-1 text-sm font-medium">{toast}</span>
          {crumbs.length > 0 && (
            <button
              onClick={() => navigate(crumbs[crumbs.length - 1].to)}
              className="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-full border border-white/30 hover:bg-white/10 transition-colors"
            >
              Regresar
            </button>
          )}
          <button onClick={() => setToast(null)} className="shrink-0 p-1 rounded-full hover:bg-white/10">
            <span className="material-symbols-outlined text-base">close</span>
          </button>
        </div>
      )}

    </div>
  );
}
