import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { getNavCrumbs } from '../navState';
import { getUserPrefs } from '../hooks/useUserPrefs';
import { ScreenHeader } from '../ScreenHeader';

/* ── Tipos ────────────────────────────────────────────────── */
interface PetMin   { id: number; name: string; species: string | null; photo: string | null }
interface Service  { id: number; name: string; type: string | null; price: number; duration_minutes: number | null }
interface Operator { id: number; name: string; role: string | null; photo_url: string | null }
interface OccBooking { time: string }

/* ── Constantes de horario ───────────────────────────────── */
const START_H = 9;
const END_H   = 19;
const STEP    = 30; // minutos por slot

function buildSlots(): string[] {
  const slots: string[] = [];
  let mins = START_H * 60;
  while (mins < END_H * 60) {
    const h = String(Math.floor(mins / 60)).padStart(2, '0');
    const m = String(mins % 60).padStart(2, '0');
    slots.push(`${h}:${m}`);
    mins += STEP;
  }
  return slots;
}
const ALL_SLOTS = buildSlots();

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

  const today = useMemo(() => new Date(), []);

  /* Estado principal */
  const [pet,       setPet]       = useState<PetMin | null>(null);
  const [services,  setServices]  = useState<Service[]>([]);
  const [operators, setOperators] = useState<Operator[]>([]);
  const [selDate,   setSelDate]   = useState<Date>(() =>
    new Date(today.getFullYear(), today.getMonth(), today.getDate())
  );
  const [occupied,  setOccupied]  = useState<Set<string>>(new Set());
  const [loadSlots, setLoadSlots] = useState(false);
  const [selSlot,      setSelSlot]      = useState<string | null>(null);
  const [selSvcs,      setSelSvcs]      = useState<number[]>([]);
  const [selOp,        setSelOp]        = useState<number | null>(null);
  const [customDur,    setCustomDur]    = useState<number | null>(null); // null = usar catálogo
  const [notes,        setNotes]        = useState('');

  /* Estado de envío */
  const [saving,      setSaving]      = useState(false);
  const [saved,       setSaved]       = useState(false);
  const [saveErr,     setSaveErr]     = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  /* Refs para scroll a campos con error */
  const svcSectionRef  = useRef<HTMLElement>(null);
  const slotSectionRef = useRef<HTMLElement>(null);

  /* Carga inicial: mascota, servicios, operadores */
  useEffect(() => {
    if (!id) return;
    fetch(`/api/pets/${id}`)
      .then(r => r.json())
      .then((d: any) => setPet({ id: d.id, name: d.name, species: d.species, photo: d.photo }))
      .catch(() => {});
    fetch('/api/services').then(r => r.json()).then(setServices).catch(() => {});
    fetch('/api/operators').then(r => r.json()).then(setOperators).catch(() => {});
  }, [id]);

  /* Carga citas ocupadas al cambiar fecha */
  const loadOccupied = useCallback((date: Date) => {
    setLoadSlots(true);
    setSelSlot(null);
    fetch(`/api/agenda?date=${localDateStr(date)}`)
      .then(r => r.json())
      .then((bookings: OccBooking[]) =>
        setOccupied(new Set(bookings.map(b => b.time.slice(0, 5))))
      )
      .catch(() => setOccupied(new Set()))
      .finally(() => setLoadSlots(false));
  }, []);

  useEffect(() => { loadOccupied(selDate); }, [selDate, loadOccupied]);

  /* Duración base del catálogo según servicios elegidos */
  const catalogDuration = useMemo(() =>
    services
      .filter(s => selSvcs.includes(s.id))
      .reduce((acc, s) => acc + (s.duration_minutes ?? STEP), 0),
    [services, selSvcs]
  );

  /* Al cambiar servicios, resetear ajuste manual y limpiar error */
  useEffect(() => {
    setCustomDur(null);
    if (selSvcs.length > 0) setFieldErrors(prev => { const n = { ...prev }; delete n.services; return n; });
  }, [selSvcs]);

  /* Al seleccionar horario, limpiar error de slot */
  useEffect(() => {
    if (selSlot) setFieldErrors(prev => { const n = { ...prev }; delete n.slot; return n; });
  }, [selSlot]);

  /* Duración efectiva: la del usuario si la ajustó, sino la del catálogo (mínimo 15 min) */
  const effectiveDuration = customDur ?? (catalogDuration > 0 ? catalogDuration : STEP);
  const slotsNeeded = Math.max(1, Math.ceil(effectiveDuration / STEP));

  /* Precio total */
  const totalPrice = useMemo(() =>
    services
      .filter(s => selSvcs.includes(s.id))
      .reduce((acc, s) => acc + s.price, 0),
    [services, selSvcs]
  );

  /* Ajuste de duración */
  const DURATION_STEP = 15;
  const DURATION_MIN  = 15;
  const DURATION_MAX  = 480;
  const adjustDur = (delta: number) => {
    const base = effectiveDuration;
    setCustomDur(Math.min(DURATION_MAX, Math.max(DURATION_MIN, base + delta)));
  };
  const isDurationModified = customDur !== null && customDur !== catalogDuration;

  /* Índice del slot seleccionado */
  const selSlotIdx = selSlot ? ALL_SLOTS.indexOf(selSlot) : -1;
  const isInRange  = (slot: string) => {
    const idx = ALL_SLOTS.indexOf(slot);
    return selSlotIdx >= 0 && idx >= selSlotIdx && idx < selSlotIdx + slotsNeeded;
  };

  /* Un slot es inválido si alguno de los slots que ocuparía está taken */
  const isSlotInvalid = (slot: string) => {
    const idx = ALL_SLOTS.indexOf(slot);
    for (let i = 0; i < slotsNeeded; i++) {
      const s = ALL_SLOTS[idx + i];
      if (!s || occupied.has(s)) return true;
    }
    return false;
  };

  const toggleSvc = (svcId: number) =>
    setSelSvcs(prev =>
      prev.includes(svcId) ? prev.filter(x => x !== svcId) : [...prev, svcId]
    );

  /* ── Hora de fin estimada ──────────────────────────────── */
  const endTime = selSlot ? slotAddMins(selSlot, effectiveDuration) : null;

  /* ── Guardar ───────────────────────────────────────────── */
  const canSave = selSlot !== null && !isSlotInvalid(selSlot) && pet !== null;

  const save = async () => {
    if (saving) return;

    // Validación client-side con feedback por campo
    const errs: Record<string, string> = {};
    if (selSvcs.length === 0) errs.services = 'Selecciona al menos un servicio para continuar';
    if (!selSlot)              errs.slot     = 'Selecciona un horario';
    else if (isSlotInvalid(selSlot)) errs.slot = 'Este horario tiene conflicto con otra cita';

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

    const scheduled_at = `${localDateStr(selDate)} ${selSlot!}:00`;

    try {
      const res = await fetch('/api/bookings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          pet_id:           pet!.id,
          operator_id:      selOp,
          scheduled_at,
          duration_minutes: effectiveDuration,
          services:         selSvcs,
          notes:            notes.trim() || null,
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        const msg = data.errors
          ? Object.values(data.errors as Record<string, string[]>).flat().join(' · ')
          : (data.message ?? `Error ${res.status}`);
        setSaveErr(msg);
        setSaving(false);
        return;
      }

      setSaved(true);
      // Esperar 1.5 s para que el usuario vea la confirmación
      setTimeout(() => navigate(`/mascotas/${pet!.id}`, { replace: true }), 1500);

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
        onCrumbClick={(to) => navigate(to)}
        rightAction={
          <button
            onClick={save}
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
            {pet.species && <p className="text-xs text-on-surface-variant">{pet.species}</p>}
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
            <label
              htmlFor="free-date"
              className="p-2 rounded-xl bg-surface-container border border-outline-variant shrink-0 cursor-pointer hover:bg-surface-container-high transition-colors"
            >
              <span className="material-symbols-outlined text-on-surface-variant text-xl">calendar_month</span>
            </label>
          </div>
        </section>

        {/* ── 2. Servicios ─────────────────────────────── */}
        <section ref={svcSectionRef}>
          <div className="flex items-center justify-between mb-2">
            <p className={`text-xs font-semibold uppercase tracking-wide ${fieldErrors.services ? 'text-error' : 'text-on-surface-variant'}`}>
              Servicios {fieldErrors.services ? '· requerido' : ''}
            </p>
            {selSvcs.length > 0 && (
              <span className="text-xs text-on-surface-variant bg-surface-container border border-outline-variant px-2 py-0.5 rounded-full">
                {minutesToHHMM(catalogDuration)} · ${totalPrice.toFixed(0)}
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
              {services.map(svc => {
                const sel = selSvcs.includes(svc.id);
                return (
                  <button
                    key={svc.id}
                    onClick={() => toggleSvc(svc.id)}
                    className={`flex items-center gap-3 px-4 py-3 rounded-2xl border transition-colors text-left ${
                      sel
                        ? 'bg-primary/10 border-primary/40'
                        : fieldErrors.services
                          ? 'bg-error/5 border-error/30'
                          : 'bg-surface-container border-outline-variant'
                    }`}
                  >
                    <span
                      className={`material-symbols-outlined text-xl shrink-0 ${sel ? 'text-primary' : 'text-on-surface-variant'}`}
                      style={{ fontVariationSettings: `'FILL' ${sel ? 1 : 0}` }}
                    >
                      {sel ? 'check_circle' : 'radio_button_unchecked'}
                    </span>
                    <div className="flex-1 min-w-0">
                      <p className={`text-sm font-semibold truncate ${sel ? 'text-primary' : 'text-on-surface'}`}>
                        {svc.name}
                      </p>
                      <div className="flex items-center gap-2 mt-0.5">
                        {svc.price > 0 && (
                          <span className="text-xs text-on-surface-variant">${svc.price.toFixed(0)}</span>
                        )}
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
          )}
        </section>

        {/* ── 3. Duración ──────────────────────────────── */}
        <section>
          <div className="flex items-center justify-between mb-2">
            <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide">Duración</p>
            {isDurationModified && (
              <button
                onClick={() => setCustomDur(null)}
                className="text-xs text-primary flex items-center gap-1"
              >
                <span className="material-symbols-outlined text-sm">restart_alt</span>
                Restablecer catálogo
              </button>
            )}
          </div>

          <div className="flex items-center bg-surface-container border border-outline-variant rounded-2xl overflow-hidden">
            {/* Botón − */}
            <button
              onClick={() => adjustDur(-DURATION_STEP)}
              disabled={effectiveDuration <= DURATION_MIN}
              className="flex items-center justify-center w-14 h-14 text-on-surface active:bg-surface-container-high transition-colors disabled:opacity-30"
            >
              <span className="material-symbols-outlined text-2xl">remove</span>
            </button>

            {/* Valor central */}
            <div className="flex-1 flex flex-col items-center justify-center py-3">
              <span className="text-2xl font-bold text-on-surface tabular-nums">
                {minutesToHHMM(effectiveDuration)}
              </span>
              {!isDurationModified && catalogDuration > 0 ? (
                <span className="text-[10px] text-on-surface-variant mt-0.5">Del catálogo</span>
              ) : isDurationModified ? (
                <span className="text-[10px] text-primary mt-0.5">Ajustado manualmente</span>
              ) : (
                <span className="text-[10px] text-on-surface-variant mt-0.5">Estimado</span>
              )}
            </div>

            {/* Botón + */}
            <button
              onClick={() => adjustDur(DURATION_STEP)}
              disabled={effectiveDuration >= DURATION_MAX}
              className="flex items-center justify-center w-14 h-14 text-on-surface active:bg-surface-container-high transition-colors disabled:opacity-30"
            >
              <span className="material-symbols-outlined text-2xl">add</span>
            </button>
          </div>
        </section>

        {/* ── 4. Operador (opcional) ───────────────────── */}
        <section>
          <p className="text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-2">
            Operador asignado
            <span className="font-normal normal-case ml-1 text-on-surface-variant/50">(opcional)</span>
          </p>
          <div className="flex flex-wrap gap-2">
            <button
              onClick={() => setSelOp(null)}
              className={`flex items-center gap-1.5 px-3 py-2 rounded-full text-sm border transition-colors ${
                selOp === null
                  ? 'bg-surface-container-high border-outline text-on-surface font-semibold'
                  : 'bg-surface-container border-outline-variant text-on-surface-variant'
              }`}
            >
              <span className="material-symbols-outlined text-base">person_off</span>
              Sin asignar
            </button>
            {operators.map(op => {
              const sel = selOp === op.id;
              return (
                <button
                  key={op.id}
                  onClick={() => setSelOp(sel ? null : op.id)}
                  className={`flex items-center gap-2 px-3 py-2 rounded-full text-sm border transition-colors ${
                    sel
                      ? 'bg-primary text-on-primary border-primary'
                      : 'bg-surface-container border-outline-variant text-on-surface'
                  }`}
                >
                  {op.photo_url ? (
                    <img src={op.photo_url} className="w-5 h-5 rounded-full object-cover" alt="" />
                  ) : (
                    <span className="material-symbols-outlined text-base" style={{ fontVariationSettings: "'FILL' 1" }}>person</span>
                  )}
                  <span className="font-medium">{op.name.split(' ')[0]}</span>
                  {op.role && <span className={`text-xs ${sel ? 'opacity-80' : 'text-on-surface-variant'}`}>{op.role}</span>}
                </button>
              );
            })}
          </div>
        </section>

        {/* ── 5. Horario ───────────────────────────────── */}
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
          {loadSlots ? (
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
              </div>

              <div className="grid grid-cols-4 gap-2">
                {ALL_SLOTS.map(slot => {
                  const occ   = occupied.has(slot);
                  const isSel = selSlot === slot;
                  const inRng = isInRange(slot) && !isSel;
                  const inv   = !occ && selSlot === null && isSlotInvalid(slot) && slotsNeeded > 1;

                  return (
                    <button
                      key={slot}
                      disabled={occ}
                      onClick={() => {
                        if (isSlotInvalid(slot)) {
                          // Slot válido para empezar pero su rango se choca → mostrar igualmente, el usuario elige
                        }
                        setSelSlot(isSel ? null : slot);
                      }}
                      className={`py-2.5 rounded-xl text-sm font-mono font-semibold transition-all ${
                        occ
                          ? 'bg-error/8 text-error/40 border border-error/20 cursor-not-allowed line-through'
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

        {/* ── 6. Notas ─────────────────────────────────── */}
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
              {selSvcs.length > 0 && (
                <p className="text-xs text-on-surface-variant mt-0.5">
                  {services.filter(s => selSvcs.includes(s.id)).map(s => s.name).join(' · ')}
                </p>
              )}
              {selOp !== null && (
                <p className="text-xs text-on-surface-variant mt-0.5 flex items-center gap-1">
                  <span className="material-symbols-outlined text-xs">person</span>
                  {operators.find(o => o.id === selOp)?.name}
                </p>
              )}
              {totalPrice > 0 && (
                <p className="text-xs font-semibold text-on-surface-variant mt-0.5">${totalPrice.toFixed(0)}</p>
              )}
            </div>
          </div>
        )}

      </div>

      {/* ── Banner fijo: error de servidor ───────────── */}
      {saveErr && (
        <div className="fixed bottom-32 left-4 right-4 z-40 bg-error text-on-error rounded-2xl px-4 py-3 flex items-start gap-2 shadow-lg">
          <span className="material-symbols-outlined text-xl shrink-0 mt-0.5" style={{ fontVariationSettings: "'FILL' 1" }}>error</span>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-bold leading-tight">No se pudo agendar</p>
            <p className="text-xs mt-0.5 opacity-90">{saveErr}</p>
          </div>
          <button onClick={() => setSaveErr(null)} className="shrink-0 p-1 rounded-full hover:bg-white/20">
            <span className="material-symbols-outlined text-base">close</span>
          </button>
        </div>
      )}

      {/* ── Botón flotante de acción ─────────────────── */}
      <div className="fixed bottom-16 left-4 right-4 z-30">
        <button
          onClick={save}
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

    </div>
  );
}
