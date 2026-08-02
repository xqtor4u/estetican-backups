import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useSortable, SortBtn } from '../hooks/useSortable';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { getNavCrumbs, setNavCrumbs } from '../navState';
import { ScreenHeader } from '../ScreenHeader';
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
    operator_id: number | null;
    operator_name: string | null;
    is_external: boolean;
    external_cost: number | null;
  }[];
  operator: { id: number; name: string; photo_url: string | null } | null;
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
  scheduled:     'bg-primary/10 text-primary border-primary/30',
  work_order:    'bg-secondary-container text-on-secondary-container border-secondary-fixed',
  completed:     'bg-tertiary-container/40 text-on-tertiary-container border-tertiary-fixed-dim',
  cancelled:     'bg-error/10 text-error border-error/30',
  no_show:       'bg-error/10 text-error border-error/30',
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
    { value: 'work_order',    label: 'Iniciar servicio', icon: 'play_arrow' },
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
    const svcRows: TxRow[] = booking.services.map((s, i) => ({
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

  /* Guardado / error de carga */
  const [saving,    setSaving]    = useState(false);
  const [saveErr,   setSaveErr]   = useState<string | null>(null);
  const [offerOverride, setOfferOverride] = useState(false);
  const [loadErr,   setLoadErr]   = useState<string | null>(null);

  /* ── Carga inicial ─────────────────────────────────────── */
  useEffect(() => {
    if (!id) return;

    const load = async () => {
      try {
        const [bRes, svcsRes, opsRes, settingsRes] = await Promise.all([
          fetch(`/api/bookings/${id}`),
          fetch('/api/services'),
          fetch('/api/operators'),
          fetch('/api/settings/booking'),
        ]);

        if (!bRes.ok) {
          const err = await bRes.json().catch(() => ({}));
          setLoadErr(err.message ?? `Error ${bRes.status}`);
          return;
        }

        const [b, svcs, ops, settingsData]: [BookingDetail, ServiceCat[], OperatorCat[], { grace_minutes: number }] = await Promise.all([
          bRes.json(),
          svcsRes.json(),
          opsRes.json(),
          settingsRes.ok ? settingsRes.json() : Promise.resolve({ grace_minutes: 15 }),
        ]);
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
     sin la fricción de horario de "Iniciar servicio". Si aún no se había
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
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-28">

      <ScreenHeader
        title={`Cita #${booking.id}`}
        screenTag="MobCitaDet"
        subtitle={booking.pet.name}
        backIcon={editing ? 'close' : 'arrow_back'}
        onBack={() => editing ? setEditing(false) : navigate(-1)}
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

        {/* ── Info rápida: estado + hora ────────────────── */}
        <div className="flex items-center gap-3 flex-wrap">
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
          <span className="flex items-center gap-1.5 font-mono text-sm font-semibold text-on-surface">
            <span className="material-symbols-outlined text-base text-on-surface-variant">schedule</span>
            {booking.time}{booking.end_time ? ` → ${booking.end_time}` : ''}
            {booking.duration_minutes
              ? <span className="text-xs font-normal text-on-surface-variant ml-1">({minutesToHHMM(booking.duration_minutes)})</span>
              : null}
          </span>
          {booking.total > 0 && (
            <span className="ml-auto text-sm font-bold text-on-surface">${booking.total.toFixed(0)}</span>
          )}
        </div>

        {/* ── Mascota y cliente ─────────────────────────── */}
        <div className="bg-surface border border-outline-variant rounded-2xl overflow-hidden">
          <button onClick={() => { const nc = [{ label: `Cita #${booking.id}`, to: `/citas/${booking.id}` }]; setNavCrumbs(nc); navigate(`/mascotas/${booking.pet.id}`, { state: { _crumbs: nc } }); }}
            className="w-full flex items-center gap-3 px-4 py-3 active:bg-surface-container transition-colors">
            <div className="w-12 h-12 rounded-xl bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
              {booking.pet.photo
                ? <img src={booking.pet.photo} className="w-full h-full object-cover" alt={booking.pet.name} />
                : <span className="material-symbols-outlined text-primary text-2xl" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>}
            </div>
            <div className="flex-1 min-w-0 text-left">
              <p className="font-semibold text-on-surface">{booking.pet.name}</p>
              {booking.pet.breed && <p className="text-xs text-on-surface-variant truncate">{booking.pet.breed}</p>}
            </div>
            <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
          </button>

          {booking.client && (
            <button onClick={() => { const nc = [{ label: `Cita #${booking.id}`, to: `/citas/${booking.id}` }]; setNavCrumbs(nc); navigate(`/clientes/${booking.client!.id}`, { state: { _crumbs: nc } }); }}
              className="w-full flex items-center gap-3 px-4 py-3 border-t border-outline-variant active:bg-surface-container transition-colors">
              <span className="material-symbols-outlined text-on-surface-variant text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
              <div className="flex-1 text-left min-w-0">
                <p className="text-sm font-medium text-on-surface truncate">{booking.client.name}</p>
              </div>
              <span className="material-symbols-outlined text-on-surface-variant">chevron_right</span>
            </button>
          )}
        </div>

        {/* ── Cambio de estado (solo vista) ────────────── */}
        {!editing && editable && NEXT_STATUSES[booking.status] && (
          <section>
            <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">Acciones</p>
            <div className="flex flex-col gap-2">
              {NEXT_STATUSES[booking.status].map(action => (
                <button
                  key={action.value}
                  disabled={saving}
                  onClick={() => {
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
                      if (Math.abs(diffMin) > graceMinutes) {
                        setGraceDialog({ diffMinutes: diffMin });
                        return;
                      }
                    }
                    changeStatus(action.value);
                  }}
                  className={`flex items-center gap-3 px-4 py-3.5 rounded-2xl border font-semibold text-sm transition-colors active:scale-[0.99] disabled:opacity-50 ${
                    action.danger
                      ? 'bg-error/8 border-error/30 text-error'
                      : 'bg-primary/8 border-primary/30 text-primary'
                  }`}
                >
                  <span className="material-symbols-outlined text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>
                    {action.icon}
                  </span>
                  {action.label}
                  {saving && <span className="ml-auto material-symbols-outlined animate-spin text-base">progress_activity</span>}
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
                    ? `La cita era a las ${booking.time}. ¿Confirmas que el servicio está comenzando ahora?`
                    : `La cita es a las ${booking.time}. ¿Confirmas iniciar el servicio antes de la hora programada?`}
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

        {/* ── Panel: asignar profesional / costo externo por línea ── */}
        {assigningLine && (
          <div className="bg-secondary/8 border border-secondary/30 rounded-2xl px-4 py-4 flex flex-col gap-3">
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-secondary text-xl">person_add</span>
              <p className="text-sm font-semibold text-secondary">Asignar: {assigningLine.name}</p>
            </div>

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
                <input type="date" id="det-date" className="sr-only" value={localDateStr(selDate)}
                  onChange={e => {
                    if (e.target.value) {
                      const [y, m, d] = e.target.value.split('-').map(Number);
                      setSelDate(new Date(y, m - 1, d));
                    }
                  }} />
                <label htmlFor="det-date"
                  className="p-2 rounded-xl bg-surface-container border border-outline-variant shrink-0 cursor-pointer">
                  <span className="material-symbols-outlined text-on-surface-variant text-xl">calendar_month</span>
                </label>
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
                          occ   ? 'bg-error/8 text-error/40 border border-error/20 cursor-not-allowed line-through' :
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
            {/* Fecha y hora */}
            <div className="bg-surface border border-outline-variant rounded-2xl px-4 py-3">
              <p className="text-xs text-on-surface-variant mb-1">Fecha y hora</p>
              <p className="text-sm font-semibold text-on-surface">
                {fmtLong(parseDateLocal(booking.scheduled_at))}
              </p>
              <p className="text-sm font-mono text-on-surface">
                {booking.time}{booking.end_time ? ` → ${booking.end_time}` : ''}
                {booking.duration_minutes
                  ? <span className="text-xs font-sans text-on-surface-variant ml-2">({minutesToHHMM(booking.duration_minutes)})</span>
                  : null}
              </p>
            </div>

            {/* ── Profesional por servicio (solo mientras está "En proceso") ── */}
            {booking.status === 'work_order' && booking.services.length > 0 && (
              <section>
                <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">Servicios y profesionales</p>
                <div className="flex flex-col gap-2">
                  {booking.services.map(line => (
                    <div key={line.booking_service_id} className="bg-surface border border-outline-variant rounded-2xl px-4 py-3 flex items-center justify-between gap-2">
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-on-surface truncate">{line.name}</p>
                        <div className="flex items-center gap-1.5 mt-1 flex-wrap">
                          {line.operator_name ? (
                            <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-primary/10 text-primary">
                              {line.operator_name}
                            </span>
                          ) : (
                            <span className="text-xs text-on-surface-variant">Sin profesional asignado</span>
                          )}
                          {line.is_external && (
                            <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-error/10 text-error border border-error/30">
                              Externo{line.external_cost !== null ? ` — $${line.external_cost.toFixed(2)}` : ''}
                            </span>
                          )}
                        </div>
                      </div>
                      <button onClick={() => openAssign(line)}
                        className="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-xl border border-secondary text-secondary">
                        Asignar
                      </button>
                    </div>
                  ))}
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

            {/* Operador */}
            {booking.operator && (
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
