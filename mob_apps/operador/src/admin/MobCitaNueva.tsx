import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { consumeCitaPresetDate, consumeCitaPresetOperator, getNavCrumbs, setNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { useAuth } from '../AuthContext';
import { ScreenHeader } from '../ScreenHeader';
import { ServicePickerSheet } from './ServicePickerSheet';

/* ── Tipos ────────────────────────────────────────────────── */
interface PetMin   { id: number; name: string; species: string | null; breed: string | null; size: string | null; weight_kg: number | string | null; photo: string | null }

/** Etiqueta legible para el tamaño (`pets.size`) — incluye valores legado en español. */
const SIZE_LABEL: Record<string, string> = { small: 'Pequeño', medium: 'Mediano', large: 'Grande', giant: 'Gigante' };
interface Service  { id: number; name: string; type: string | null; price: number; duration_minutes: number | null; operator_role_id: number | null }
interface Operator { id: number; name: string; role: string | null; photo_url: string | null; role_ids: number[] }
interface OccBooking { time: string; end_time: string | null; duration_minutes: number | null }

/** Una línea de la cita: un servicio con su operador propio (obligatorio), precio y duración
 *  editables (ambos arrancan del catálogo). */
interface BookingLine { serviceId: number; price: number; operatorId: number | null; durationMinutes: number }

/* ── Constantes de horario ───────────────────────────────── */
const STEP = 30; // minutos por slot
const DEFAULT_OPEN_MIN  = 9 * 60;
const DEFAULT_CLOSE_MIN = 19 * 60;

/** Debe coincidir exactamente con el mensaje de OperatorAvailabilityChecker::isOutsideWorkingHours en el backend. */
const SCHEDULE_OVERRIDE_MESSAGE = 'El operador seleccionado no labora en el horario indicado.';

function hhmmToMinutes(hhmm: string): number {
  const [h, m] = hhmm.split(':').map(Number);
  return h * 60 + (m || 0);
}

function buildSlots(startMin: number, endMin: number): string[] {
  const slots: string[] = [];
  let mins = startMin;
  while (mins < endMin) {
    const h = String(Math.floor(mins / 60)).padStart(2, '0');
    const m = String(mins % 60).padStart(2, '0');
    slots.push(`${h}:${m}`);
    mins += STEP;
  }
  return slots;
}

/* ── Helpers de fecha ────────────────────────────────────── */
function localDateStr(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}
function addDays(d: Date, n: number): Date {
  const r = new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
  return r;
}
function fmtChip(d: Date, offset: number): string {
  if (offset === 0) return 'Hoy';
  if (offset === 1) return 'Mañana';
  return d.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric' });
}
function fmtLong(d: Date): string {
  return d.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
}
function minutesToHHMM(mins: number): string {
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  if (h === 0) return `${m} min`;
  if (m === 0) return `${h} h`;
  return `${h} h ${m} min`;
}
function slotAddMins(slot: string, mins: number): string {
  const [h, m] = slot.split(':').map(Number);
  const total = h * 60 + m + mins;
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

/* ══════════════════════════════════════════════════════════ */
export function MobCitaNueva() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const crumbs = getNavCrumbs();
  const { showBreadcrumbs } = getUserPrefs();
  const { user } = useAuth();

  const today = useMemo(() => new Date(), []);

  /* Estado principal */
  const [pet,       setPet]       = useState<PetMin | null>(null);
  const [services,  setServices]  = useState<Service[]>([]);
  const [operators, setOperators] = useState<Operator[]>([]);
  const [selDate,   setSelDate]   = useState<Date>(() => {
    // Si venimos de Agenda con una fecha ya elegida (flujo + Cita), arrancar ahí en vez
    // de en HOY — antes esta pantalla siempre ignoraba la fecha de Agenda y el operador
    // terminaba agendando en un día distinto al que estaba viendo sin darse cuenta.
    const preset = consumeCitaPresetDate();
    if (preset) {
      const [y, m, d] = preset.split('-').map(Number);
      return new Date(y, m - 1, d);
    }
    return new Date(today.getFullYear(), today.getMonth(), today.getDate());
  });
  const [occupied,     setOccupied]     = useState<Set<string>>(new Set());
  const [blockedSlots, setBlockedSlots] = useState<Set<string>>(new Set());
  const [loadSlots, setLoadSlots] = useState(false);
  const [businessHours, setBusinessHours] = useState({ start: DEFAULT_OPEN_MIN, end: DEFAULT_CLOSE_MIN });
  const [selSlot,      setSelSlot]      = useState<string | null>(null);
  // Líneas de la cita, en orden de agregado. La primera manda: su operador es el "responsable"
  // de la cita (operator_id a nivel booking) y contra su agenda se muestra la disponibilidad.
  // El backend valida cada operador contra su propio tramo de tiempo al guardar (SYNC-040).
  const [lines,        setLines]        = useState<BookingLine[]>([]);
  const [pickerOpen,   setPickerOpen]   = useState(false);
  // Operador preferido para autocompletar líneas nuevas: el que viene del filtro de Agenda /
  // GroomerAgenda si lo hay, si no el usuario logueado cuando es operador. Solo se usa si está
  // calificado para el servicio de la línea.
  const [preferredOperatorId] = useState<number | null>(() => consumeCitaPresetOperator());
  const [notes,        setNotes]        = useState('');

  /* Estado de envío */
  const [saving,      setSaving]      = useState(false);
  const [saved,       setSaved]       = useState(false);
  const [saveErr,     setSaveErr]     = useState<string | null>(null);
  const [offerOverride, setOfferOverride] = useState(false);
  const [coverageWarning, setCoverageWarning] = useState<string | null>(null);
  const [vaccinationWarning, setVaccinationWarning] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  /* Refs para scroll a campos con error */
  const svcSectionRef  = useRef<HTMLElement>(null);
  const slotSectionRef = useRef<HTMLElement>(null);
  const freeDateInputRef = useRef<HTMLInputElement>(null);

  /* Carga inicial: mascota, servicios, operadores */
  useEffect(() => {
    if (!id) return;
    fetch(`/api/pets/${id}`)
      .then(r => r.json())
      .then((d: any) => setPet({ id: d.id, name: d.name, species: d.species ?? null, breed: d.breed ?? null, size: d.size ?? null, weight_kg: d.weight_kg ?? null, photo: d.photo ?? null }))
      .catch(() => {});
    fetch('/api/services').then(r => r.json()).then(setServices).catch(() => {});
    fetch('/api/operators').then(r => r.json()).then(setOperators).catch(() => {});
    fetch('/api/settings/booking')
      .then(r => r.json())
      .then((d: { opening_time?: string; closing_time?: string }) => {
        if (d.opening_time && d.closing_time) {
          setBusinessHours({ start: hhmmToMinutes(d.opening_time), end: hhmmToMinutes(d.closing_time) });
        }
      })
      .catch(() => {});
  }, [id]);

  const ALL_SLOTS = useMemo(
    () => buildSlots(businessHours.start, businessHours.end),
    [businessHours]
  );

  /* Carga citas ocupadas al cambiar fecha u operador — requiere operador elegido */
  const loadOccupied = useCallback((date: Date, operatorId: number | null) => {
    if (!operatorId) {
      setOccupied(new Set());
      setBlockedSlots(new Set());
      setLoadSlots(false);
      return;
    }
    setLoadSlots(true);
    setSelSlot(null);
    fetch(`/api/agenda?date=${localDateStr(date)}&operator_id=${operatorId}`)
      .then(r => r.json())
      .then((bookings: OccBooking[]) => {
        const occ = new Set<string>();
        bookings.forEach(b => {
          const startMin = hhmmToMinutes(b.time.slice(0, 5));
          const durMin = b.duration_minutes ?? STEP;
          const endMin = b.end_time ? hhmmToMinutes(b.end_time.slice(0, 5)) : startMin + durMin;
          buildSlots(startMin, Math.max(endMin, startMin + STEP)).forEach(s => occ.add(s));
        });
        setOccupied(occ);
      })
      .catch(() => setOccupied(new Set()))
      .finally(() => setLoadSlots(false));

    const dayStr = localDateStr(date);
    fetch(`/api/agenda/unavailabilities?date=${dayStr}&operator_id=${operatorId}`)
      .then(r => r.ok ? r.json() : [])
      .then((windows: { starts_at: string; ends_at: string }[]) => {
        const blocked = new Set<string>();
        windows.forEach(w => {
          const startDay = w.starts_at.slice(0, 10);
          const endDay = w.ends_at.slice(0, 10);
          const startMin = startDay < dayStr ? 0 : hhmmToMinutes(w.starts_at.slice(11, 16));
          const endMin = endDay > dayStr ? 24 * 60 : hhmmToMinutes(w.ends_at.slice(11, 16));
          buildSlots(startMin, Math.max(endMin, startMin + STEP)).forEach(s => blocked.add(s));
        });
        setBlockedSlots(blocked);
      })
      .catch(() => setBlockedSlots(new Set()));
  }, []);

  /* La primera línea manda: su operador es el "responsable" de la cita (operator_id a nivel
     booking) y contra su agenda se muestra la disponibilidad de horarios. */
  const primaryOperator = lines[0]?.operatorId ?? null;

  useEffect(() => { loadOccupied(selDate, primaryOperator); }, [selDate, primaryOperator, loadOccupied]);

  /* Duración total de la cita = suma de la duración (editable) de cada línea */
  const totalDuration = useMemo(() =>
    lines.reduce((acc, l) => acc + (l.durationMinutes || 0), 0),
    [lines]
  );

  /* Al cambiar las líneas, limpiar el error de servicios si ya están completas */
  useEffect(() => {
    if (lines.length > 0 && lines.every(l => l.operatorId != null)) {
      setFieldErrors(prev => { const n = { ...prev }; delete n.services; return n; });
    }
  }, [lines]);

  /* Al seleccionar horario, limpiar error de slot */
  useEffect(() => {
    if (selSlot) setFieldErrors(prev => { const n = { ...prev }; delete n.slot; return n; });
  }, [selSlot]);

  /* Al cambiar el operador responsable, el horario ya elegido puede dejar de ser válido */
  useEffect(() => {
    setSelSlot(null);
  }, [primaryOperator]);

  /* Duración efectiva de la cita (mínimo un slot) */
  const effectiveDuration = totalDuration > 0 ? totalDuration : STEP;
  const slotsNeeded = Math.max(1, Math.ceil(effectiveDuration / STEP));

  /* Precio total — suma del precio (editable) de cada línea */
  const totalPrice = useMemo(() =>
    lines.reduce((acc, l) => acc + (l.price || 0), 0),
    [lines]
  );

  /* Servicios que todavía se pueden agregar (los que no están ya en una línea) */
  const availableServices = useMemo(
    () => services.filter(s => !lines.some(l => l.serviceId === s.id)),
    [services, lines]
  );

  const LINE_DUR_STEP = 5;
  const LINE_DUR_MIN  = 5;
  const LINE_DUR_MAX  = 480;

  /* Índice del slot seleccionado */
  const selSlotIdx = selSlot ? ALL_SLOTS.indexOf(selSlot) : -1;
  const isInRange  = (slot: string) => {
    const idx = ALL_SLOTS.indexOf(slot);
    return selSlotIdx >= 0 && idx >= selSlotIdx && idx < selSlotIdx + slotsNeeded;
  };

  /* Un slot es inválido si alguno de los slots que ocuparía está taken u ocupado por otra cita */
  const isSlotInvalid = (slot: string) => {
    const idx = ALL_SLOTS.indexOf(slot);
    for (let i = 0; i < slotsNeeded; i++) {
      const s = ALL_SLOTS[idx + i];
      if (!s || occupied.has(s) || blockedSlots.has(s)) return true;
    }
    return false;
  };

  /* ── Líneas de servicio ────────────────────────────────── */
  const qualifiedOperators = useCallback((svc: Service | undefined): Operator[] =>
    svc == null
      ? operators
      : operators.filter(o => svc.operator_role_id == null || o.role_ids.includes(svc.operator_role_id)),
    [operators]
  );

  /** Operador por defecto para una línea nueva: el preferido (filtro de Agenda / usuario
   *  logueado) si está calificado; si no, el único calificado cuando hay exactamente uno. */
  const defaultOperatorFor = useCallback((svc: Service | undefined): number | null => {
    const qs = qualifiedOperators(svc);
    if (qs.length === 0) return null;
    const pref = preferredOperatorId ?? (user?.operator_id ?? null);
    if (pref != null && qs.some(o => o.id === pref)) return pref;
    return qs.length === 1 ? qs[0].id : null;
  }, [qualifiedOperators, preferredOperatorId, user]);

  const addLine = (svcId: number) => {
    setLines(prev => {
      if (prev.some(l => l.serviceId === svcId)) return prev;
      const svc = services.find(s => s.id === svcId);
      return [...prev, {
        serviceId: svcId,
        price: svc?.price ?? 0,
        operatorId: defaultOperatorFor(svc),
        durationMinutes: svc?.duration_minutes ?? STEP,
      }];
    });
    setPickerOpen(false);
  };

  const removeLine = (svcId: number) =>
    setLines(prev => prev.filter(l => l.serviceId !== svcId));

  const setLinePrice = (svcId: number, price: number) =>
    setLines(prev => prev.map(l => (l.serviceId === svcId ? { ...l, price } : l)));

  const setLineOperator = (svcId: number, operatorId: number | null) =>
    setLines(prev => prev.map(l => (l.serviceId === svcId ? { ...l, operatorId } : l)));

  const adjustLineDuration = (svcId: number, delta: number) =>
    setLines(prev => prev.map(l => (
      l.serviceId === svcId
        ? { ...l, durationMinutes: Math.min(LINE_DUR_MAX, Math.max(LINE_DUR_MIN, l.durationMinutes + delta)) }
        : l
    )));

  /* ── Hora de fin estimada ──────────────────────────────── */
  const endTime = selSlot ? slotAddMins(selSlot, effectiveDuration) : null;

  /* ── Guardar ───────────────────────────────────────────── */
  const linesComplete = lines.length > 0 && lines.every(l => l.operatorId != null);
  const canSave = linesComplete && selSlot !== null && !isSlotInvalid(selSlot) && pet !== null;

  const save = async (overrideAvailability = false) => {
    if (saving) return;

    // Validación client-side con feedback por campo
    const errs: Record<string, string> = {};
    if (lines.length === 0)                       errs.services = 'Agrega al menos un servicio para continuar';
    else if (lines.some(l => l.operatorId == null)) errs.services = 'Cada servicio necesita un operador asignado';
    if (!selSlot)                                 errs.slot     = 'Selecciona un horario';
    else if (isSlotInvalid(selSlot))             errs.slot     = 'Este horario tiene conflicto con otra cita';

    if (Object.keys(errs).length > 0) {
      setFieldErrors(errs);
      setTimeout(() => {
        if (errs.services) svcSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        else if (errs.slot) slotSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 50);
      return;
    }

    setFieldErrors({});
    setSaving(true);
    setSaveErr(null);
    setOfferOverride(false);

    const scheduled_at = `${localDateStr(selDate)} ${selSlot!}:00`;
    const servicesPayload = lines.map(l => ({
      id:               l.serviceId,
      price:            l.price,
      operator_id:      l.operatorId,
      duration_minutes: l.durationMinutes,
    }));

    try {
      const res = await fetch('/api/bookings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          pet_id:           pet!.id,
          operator_id:      primaryOperator,
          scheduled_at,
          duration_minutes: effectiveDuration,
          services:         servicesPayload,
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
        setSaving(false);
        return;
      }

      setSaved(true);
      if (data.coverage_warning) setCoverageWarning(data.coverage_warning);
      if (data.vaccination_warning) setVaccinationWarning(data.vaccination_warning);
      // Esperar más si hay alguna advertencia, para que el operador la alcance a leer
      const hasWarning = data.coverage_warning || data.vaccination_warning;
      // Volver a la Agenda del día agendado (no a la ficha de la mascota) — así el
      // operador comprueba de un vistazo que la cita quedó donde correspondía.
      setTimeout(() => navigate(`/agenda?date=${localDateStr(selDate)}`, { replace: true }), hasWarning ? 4000 : 1500);

    } catch (err) {
      setSaveErr('No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.');
      setSaving(false);
    }
  };

  /* ── Pantalla de éxito ─────────────────────────────────── */
  if (saved) {
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center gap-6 px-8 text-center pb-20">
        <div className="w-20 h-20 rounded-full bg-tertiary-container flex items-center justify-center">
          <span className="material-symbols-outlined text-5xl text-on-tertiary-container" style={{ fontVariationSettings: "'FILL' 1" }}>
            event_available
          </span>
        </div>
        <div>
          <p className="text-xl font-bold text-on-surface">¡Cita agendada!</p>
          <p className="text-sm text-on-surface-variant mt-1">
            {fmtLong(selDate)} a las {selSlot}
            {endTime && ` — ${endTime}`}
          </p>
          {pet && <p className="text-sm text-on-surface-variant">{pet.name}</p>}
        </div>
        <p className="text-xs text-on-surface-variant/60">Regresando a la ficha…</p>
      </div>
    );
  }

  /* ── Render principal ─────────────────────────────────── */
  return (
    <div className="bg-background text-on-background min-h-screen flex flex-col pb-28">

      <ScreenHeader
        title="Nueva cita"
        screenTag="MobCitaNueva"
        subtitle={pet ? `${pet.name}${pet.species ? ` · ${pet.species}` : ''}` : undefined}
        onBack={() => navigate(-1)}
        crumbs={crumbs}
        showBreadcrumbs={showBreadcrumbs}
        onCrumbClick={(to, prev) => { setNavCrumbs(prev); navigate(to, { state: { _crumbs: prev } }); }}
        rightAction={
          <button
            onClick={() => save()}
            disabled={!canSave || saving}
            className="flex items-center gap-1.5 bg-primary text-on-primary px-4 py-2 rounded-full text-sm font-semibold active:scale-95 transition-all disabled:opacity-40 disabled:scale-100"
          >
            {saving ? (
              <><span className="material-symbols-outlined text-base animate-spin">progress_activity</span>Guardando…</>
            ) : (
              <><span className="material-symbols-outlined text-base">event_available</span>Agendar</>
            )}
          </button>
        }
      />

      {/* ── Banner mascota ───────────────────────────────── */}
      {pet && (
        <div className="bg-surface border-b border-outline-variant px-4 py-3 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
            {pet.photo
              ? <img src={pet.photo} className="w-full h-full object-cover" alt={pet.name} />
              : <span className="material-symbols-outlined text-primary text-xl" style={{ fontVariationSettings: "'FILL' 1" }}>pets</span>
            }
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-on-surface truncate">{pet.name}</p>
            {(pet.species || pet.breed || pet.size || pet.weight_kg != null) && (
              <p className="text-xs text-on-surface-variant truncate">
                {[
                  pet.species,
                  pet.breed,
                  pet.size ? (SIZE_LABEL[pet.size] ?? pet.size) : null,
                  pet.weight_kg != null ? `${Number(pet.weight_kg)} kg` : null,
                ].filter(Boolean).join(' · ')}
              </p>
            )}
          </div>
        </div>
      )}

      <div className="flex flex-col gap-6 px-4 pt-5">

        {/* ── 1. Fecha ─────────────────────────────────── */}
        <section>
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">Fecha</p>
          <div className="flex items-center gap-2 overflow-x-auto hide-scrollbar pb-1">
            {[0, 1, 2, 3, 4, 5, 6].map(offset => {
              const d   = addDays(today, offset);
              const sel = localDateStr(d) === localDateStr(selDate);
              return (
                <button
                  key={offset}
                  onClick={() => setSelDate(d)}
                  className={`flex flex-col items-center px-3 py-2 rounded-xl shrink-0 transition-colors ${
                    sel
                      ? 'bg-primary text-on-primary'
                      : 'bg-surface-container text-on-surface border border-outline-variant'
                  }`}
                >
                  <span className="text-[10px] uppercase tracking-wide opacity-70 leading-tight">
                    {fmtChip(d, offset)}
                  </span>
                  <span className="text-sm font-bold leading-tight">{d.getDate()}</span>
                </button>
              );
            })}
            <input
              ref={freeDateInputRef}
              type="date"
              id="free-date"
              className="sr-only"
              value={localDateStr(selDate)}
              onChange={e => {
                if (e.target.value) {
                  const [y, m, d] = e.target.value.split('-').map(Number);
                  setSelDate(new Date(y, m - 1, d));
                }
              }}
            />
            <button
              type="button"
              onClick={() => {
                // En Chrome de escritorio, enfocar el input (ej. vía label) no abre el
                // calendario nativo — solo clicar el ícono propio del input lo hace, y ese
                // ícono no es visible porque el input está oculto (sr-only). showPicker() lo
                // abre explícitamente. En Android el foco ya abre el selector nativo solo,
                // por eso este botón nunca hacía falta ahí.
                const el = freeDateInputRef.current;
                if (el && typeof el.showPicker === 'function') el.showPicker();
                else el?.focus();
              }}
              className="p-2 rounded-xl bg-surface-container border border-outline-variant shrink-0 cursor-pointer hover:bg-surface-container-high transition-colors"
              aria-label="Elegir otra fecha del calendario"
            >
              <span className="material-symbols-outlined text-on-surface-variant text-xl">calendar_month</span>
            </button>
          </div>
        </section>

        {/* ── 2. Servicios + operador por línea ────────── */}
        <section ref={svcSectionRef}>
          <div className="flex items-center justify-between mb-2">
            <p className={`text-xs font-semibold uppercase tracking-wide ${fieldErrors.services ? 'text-error' : 'text-on-surface-variant'}`}>
              Servicios {fieldErrors.services ? '· requerido' : ''}
            </p>
            {lines.length > 0 && (
              <span className="text-xs text-on-surface-variant bg-surface-container border border-outline-variant px-2 py-0.5 rounded-full">
                {minutesToHHMM(totalDuration)} · ${totalPrice.toFixed(0)}
              </span>
            )}
          </div>

          {fieldErrors.services && (
            <p className="text-xs text-error flex items-center gap-1 mb-2">
              <span className="material-symbols-outlined text-sm" style={{ fontVariationSettings: "'FILL' 1" }}>error</span>
              {fieldErrors.services}
            </p>
          )}

          {services.length === 0 ? (
            <p className="text-sm text-on-surface-variant italic">Cargando servicios…</p>
          ) : (
            <div className="flex flex-col gap-2">
              {lines.map((line, idx) => {
                const svc = services.find(s => s.id === line.serviceId);
                if (!svc) return null;
                const qual = qualifiedOperators(svc);
                const missingOp = line.operatorId == null;
                return (
                  <div
                    key={line.serviceId}
                    className={`rounded-2xl border transition-colors ${
                      missingOp && fieldErrors.services
                        ? 'bg-error/5 border-error/30'
                        : 'bg-primary/10 border-primary/40'
                    }`}
                  >
                    <div className="flex items-start gap-2 px-4 pt-3 pb-2">
                      <p className="flex-1 min-w-0 text-sm font-semibold text-primary pt-1 break-words">{svc.name}</p>
                      {idx === 0 && !missingOp && (
                        <span className="text-[10px] uppercase tracking-wide text-on-surface-variant shrink-0 pt-1.5">responsable</span>
                      )}
                      <button
                        type="button"
                        onClick={() => removeLine(line.serviceId)}
                        aria-label={`Quitar ${svc.name}`}
                        className="shrink-0 w-8 h-8 flex items-center justify-center rounded-full text-on-surface-variant active:bg-surface-container-high transition-colors"
                      >
                        <span className="material-symbols-outlined text-xl">close</span>
                      </button>
                    </div>

                    <div className="px-4 pb-3 flex items-center gap-2 flex-wrap">
                      {/* Duración de la línea (arranca del catálogo, editable) */}
                      <div className="flex items-center bg-surface rounded-lg border border-outline-variant overflow-hidden shrink-0 h-8">
                        <button
                          type="button"
                          onClick={() => adjustLineDuration(line.serviceId, -LINE_DUR_STEP)}
                          disabled={line.durationMinutes <= LINE_DUR_MIN}
                          className="w-7 h-8 flex items-center justify-center text-on-surface active:bg-surface-container-high disabled:opacity-30"
                        >
                          <span className="material-symbols-outlined text-base">remove</span>
                        </button>
                        <span className="text-xs font-semibold text-on-surface tabular-nums px-1 min-w-[3.25rem] text-center">
                          {minutesToHHMM(line.durationMinutes)}
                        </span>
                        <button
                          type="button"
                          onClick={() => adjustLineDuration(line.serviceId, LINE_DUR_STEP)}
                          disabled={line.durationMinutes >= LINE_DUR_MAX}
                          className="w-7 h-8 flex items-center justify-center text-on-surface active:bg-surface-container-high disabled:opacity-30"
                        >
                          <span className="material-symbols-outlined text-base">add</span>
                        </button>
                      </div>

                      {/* Precio de la línea */}
                      <div className="flex items-center gap-1 shrink-0 bg-surface rounded-lg border border-outline-variant px-2 h-8">
                        <span className="text-xs text-on-surface-variant">$</span>
                        <input
                          type="number"
                          min={0}
                          step="0.01"
                          inputMode="decimal"
                          value={line.price}
                          onChange={e => setLinePrice(line.serviceId, e.target.value === '' ? 0 : Number(e.target.value))}
                          className="w-14 bg-transparent text-sm font-semibold text-on-surface text-right focus:outline-none"
                        />
                      </div>
                    </div>

                    <div className="px-4 pb-3 flex items-center gap-2">
                      <span className="material-symbols-outlined text-sm text-on-surface-variant shrink-0">person</span>
                      {qual.length === 0 ? (
                        <span className="text-xs text-error">
                          Ningún operador activo está calificado para este servicio
                        </span>
                      ) : (
                        <select
                          value={line.operatorId ?? ''}
                          onChange={e => setLineOperator(line.serviceId, e.target.value ? Number(e.target.value) : null)}
                          className={`flex-1 min-w-0 bg-surface border rounded-lg px-2 py-1.5 text-xs outline-none focus:border-primary transition-colors ${
                            missingOp ? 'border-error/50 text-error' : 'border-outline-variant text-on-surface'
                          }`}
                        >
                          <option value="">Elegir operador…</option>
                          {qual.map(op => (
                            <option key={op.id} value={op.id}>{op.name}</option>
                          ))}
                        </select>
                      )}
                    </div>
                  </div>
                );
              })}

              {availableServices.length > 0 && (
                <button
                  type="button"
                  onClick={() => setPickerOpen(true)}
                  className="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl border border-dashed border-primary/50 text-primary text-sm font-semibold active:bg-primary/5 transition-colors"
                >
                  <span className="material-symbols-outlined text-xl">add</span>
                  Agregar servicio
                </button>
              )}
            </div>
          )}
        </section>

        {/* La duración se ajusta por línea de servicio (sección 2); la duración total de la
            cita es la suma. El operador también se elige por línea — el de la primera línea es
            el "responsable" de la cita y contra su agenda se muestra la disponibilidad. */}

        {/* ── 3. Horario ───────────────────────────────── */}
        <section ref={slotSectionRef}>
          <div className="flex items-center justify-between mb-2">
            <p className={`text-xs font-semibold uppercase tracking-wide ${fieldErrors.slot ? 'text-error' : 'text-on-surface-variant'}`}>
              Horario — {fmtLong(selDate)} {fieldErrors.slot ? '· requerido' : ''}
            </p>
            {selSlot && endTime && (
              <span className="text-xs text-primary font-semibold">
                {selSlot} → {endTime}
              </span>
            )}
          </div>

          {fieldErrors.slot && (
            <p className="text-xs text-error flex items-center gap-1 mb-2">
              <span className="material-symbols-outlined text-sm" style={{ fontVariationSettings: "'FILL' 1" }}>error</span>
              {fieldErrors.slot}
            </p>
          )}
          {primaryOperator === null ? (
            <div className="flex items-center gap-2 py-6 text-on-surface-variant text-sm bg-surface-container border border-outline-variant rounded-2xl px-4">
              <span className="material-symbols-outlined text-xl">person_off</span>
              Agrega un servicio y elige su operador para ver la disponibilidad.
            </div>
          ) : loadSlots ? (
            <div className="flex items-center gap-2 py-6 text-on-surface-variant text-sm">
              <span className="material-symbols-outlined animate-spin text-xl">progress_activity</span>
              Verificando disponibilidad…
            </div>
          ) : (
            <>
              {/* Leyenda */}
              <div className="flex items-center gap-4 mb-3 text-[11px] text-on-surface-variant">
                <span className="flex items-center gap-1.5">
                  <span className="w-3 h-3 rounded bg-primary inline-block" />
                  Seleccionado
                </span>
                <span className="flex items-center gap-1.5">
                  <span className="w-3 h-3 rounded bg-primary/30 inline-block" />
                  Duración
                </span>
                <span className="flex items-center gap-1.5">
                  <span className="w-3 h-3 rounded bg-error/20 inline-block" />
                  Ocupado
                </span>
                <span className="flex items-center gap-1.5">
                  <span className="w-3 h-3 rounded bg-on-surface-variant/20 inline-block" />
                  Bloqueado
                </span>
              </div>

              <div className="grid grid-cols-4 gap-2">
                {ALL_SLOTS.map(slot => {
                  const occ     = occupied.has(slot);
                  const blocked = !occ && blockedSlots.has(slot);
                  const isSel = selSlot === slot;
                  const inRng = isInRange(slot) && !isSel;
                  const inv   = !occ && !blocked && selSlot === null && isSlotInvalid(slot) && slotsNeeded > 1;

                  return (
                    <button
                      key={slot}
                      disabled={occ || blocked}
                      title={blocked ? 'Operador no disponible (vacaciones/permiso)' : undefined}
                      onClick={() => {
                        if (isSlotInvalid(slot)) {
                          // Slot válido para empezar pero su rango se choca → mostrar igualmente, el usuario elige
                        }
                        setSelSlot(isSel ? null : slot);
                      }}
                      className={`py-2.5 rounded-xl text-sm font-mono font-semibold transition-all ${
                        occ
                          ? 'bg-error-container text-on-error-container border border-error/20 cursor-not-allowed line-through'
                          : blocked
                            ? 'bg-surface-container-high text-on-surface-variant border border-outline-variant cursor-not-allowed line-through'
                            : isSel
                              ? 'bg-primary text-on-primary shadow-md scale-105'
                              : inRng
                                ? 'bg-primary/25 text-primary border border-primary/30'
                                : 'bg-surface-container text-on-surface border border-outline-variant active:scale-95 hover:border-primary/40'
                      }`}
                    >
                      {slot}
                    </button>
                  );
                })}
              </div>

              {selSlot && isSlotInvalid(selSlot) && (
                <p className="text-xs text-error mt-2 flex items-center gap-1">
                  <span className="material-symbols-outlined text-sm">warning</span>
                  Este horario se traslapa con una cita existente para la duración seleccionada
                </p>
              )}
            </>
          )}
        </section>

        {/* ── 4. Notas ─────────────────────────────────── */}
        <section>
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">Notas</p>
          <textarea
            value={notes}
            onChange={e => setNotes(e.target.value)}
            rows={3}
            placeholder="Instrucciones especiales, cuidados, comportamiento…"
            className="w-full bg-surface-container border border-outline-variant rounded-2xl px-4 py-3 text-sm text-on-surface outline-none focus:border-primary resize-none transition-colors"
          />
        </section>

        {/* ── Resumen ───────────────────────────────────── */}
        {selSlot && (
          <div className={`border rounded-2xl px-4 py-3 flex items-start gap-3 ${
            isSlotInvalid(selSlot)
              ? 'bg-error/8 border-error/30'
              : 'bg-primary/8 border-primary/20'
          }`}>
            <span
              className={`material-symbols-outlined text-xl mt-0.5 shrink-0 ${isSlotInvalid(selSlot) ? 'text-error' : 'text-primary'}`}
              style={{ fontVariationSettings: "'FILL' 1" }}
            >
              {isSlotInvalid(selSlot) ? 'warning' : 'event_available'}
            </span>
            <div>
              <p className="text-sm font-semibold text-on-surface">
                {fmtLong(selDate)} · {selSlot}{endTime ? ` → ${endTime}` : ''}
              </p>
              {lines.length > 0 && (
                <div className="text-xs text-on-surface-variant mt-0.5 flex flex-col gap-0.5">
                  {lines.map(l => {
                    const svc = services.find(s => s.id === l.serviceId);
                    const op = operators.find(o => o.id === l.operatorId);
                    return (
                      <span key={l.serviceId} className="flex items-center gap-1">
                        <span className="material-symbols-outlined text-xs">person</span>
                        {svc?.name ?? '—'}{op ? ` · ${op.name}` : ' · sin operador'}
                      </span>
                    );
                  })}
                </div>
              )}
              {totalPrice > 0 && (
                <p className="text-xs font-semibold text-on-surface-variant mt-0.5">${totalPrice.toFixed(0)}</p>
              )}
            </div>
          </div>
        )}

      </div>

      {/* ── Banner fijo: fuera de radio de cobertura ─── */}
      {coverageWarning && (
        <div className="fixed bottom-32 left-4 right-4 z-40 bg-amber-500 text-white rounded-2xl px-4 py-3 flex items-start gap-2 shadow-lg">
          <span className="material-symbols-outlined text-xl shrink-0 mt-0.5" style={{ fontVariationSettings: "'FILL' 1" }}>distance</span>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-bold leading-tight">Fuera de zona habitual</p>
            <p className="text-xs mt-0.5 opacity-90">{coverageWarning}</p>
          </div>
          <button onClick={() => setCoverageWarning(null)} className="shrink-0 p-1 rounded-full hover:bg-white/20">
            <span className="material-symbols-outlined text-base">close</span>
          </button>
        </div>
      )}

      {/* ── Banner fijo: vacunas core no vigentes ─── */}
      {vaccinationWarning && (
        <div className="fixed bottom-32 left-4 right-4 z-40 bg-amber-500 text-white rounded-2xl px-4 py-3 flex items-start gap-2 shadow-lg">
          <span className="material-symbols-outlined text-xl shrink-0 mt-0.5" style={{ fontVariationSettings: "'FILL' 1" }}>vaccines</span>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-bold leading-tight">Vacunas pendientes</p>
            <p className="text-xs mt-0.5 opacity-90">{vaccinationWarning}</p>
          </div>
          <button onClick={() => setVaccinationWarning(null)} className="shrink-0 p-1 rounded-full hover:bg-white/20">
            <span className="material-symbols-outlined text-base">close</span>
          </button>
        </div>
      )}

      {/* ── Banner fijo: error de servidor ───────────── */}
      {saveErr && (
        <div className="fixed bottom-32 left-4 right-4 z-40 bg-error text-on-error rounded-2xl px-4 py-3 flex items-start gap-2 shadow-lg">
          <span className="material-symbols-outlined text-xl shrink-0 mt-0.5" style={{ fontVariationSettings: "'FILL' 1" }}>error</span>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-bold leading-tight">No se pudo agendar</p>
            <p className="text-xs mt-0.5 opacity-90">{saveErr}</p>
            {offerOverride && (
              <button
                onClick={() => save(true)}
                className="mt-2 text-xs font-bold underline underline-offset-2"
              >
                Agendar de todas formas
              </button>
            )}
          </div>
          <button onClick={() => { setSaveErr(null); setOfferOverride(false); }} className="shrink-0 p-1 rounded-full hover:bg-white/20">
            <span className="material-symbols-outlined text-base">close</span>
          </button>
        </div>
      )}

      {/* ── Botón flotante de acción ─────────────────── */}
      <div className="fixed bottom-16 left-4 right-4 z-30">
        <button
          onClick={() => save()}
          disabled={saving}
          className="w-full flex items-center justify-center gap-2 bg-primary text-on-primary py-4 rounded-2xl text-base font-bold shadow-lg active:scale-[0.98] transition-all disabled:opacity-60 disabled:scale-100"
        >
          {saving ? (
            <>
              <span className="material-symbols-outlined animate-spin">progress_activity</span>
              Guardando cita…
            </>
          ) : selSlot && isSlotInvalid(selSlot) ? (
            <>
              <span className="material-symbols-outlined">warning</span>
              Horario con conflicto
            </>
          ) : canSave ? (
            <>
              <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>event_available</span>
              Agendar {selSlot}{endTime ? ` → ${endTime}` : ''}
            </>
          ) : (
            <>
              <span className="material-symbols-outlined">event_available</span>
              Agendar cita
            </>
          )}
        </button>
      </div>

      {pickerOpen && (
        <ServicePickerSheet
          services={availableServices}
          onPick={addLine}
          onClose={() => setPickerOpen(false)}
        />
      )}

    </div>
  );
}
